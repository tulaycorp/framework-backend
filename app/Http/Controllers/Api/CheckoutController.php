<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    /**
     * Process the checkout and create an order.
     */
    public function process(Request $request): JsonResponse
    {
        // 1. Validate the request
        $validator = Validator::make($request->all(), [
            // Shipping info
            'shipping_first_name' => 'required|string|max:100',
            'shipping_last_name' => 'required|string|max:100',
            'shipping_email' => 'required|email|max:255',
            'shipping_phone' => 'nullable|string|max:20',
            'shipping_address1' => 'required|string|max:255',
            'shipping_address2' => 'nullable|string|max:255',
            'shipping_city' => 'required|string|max:100',
            'shipping_state' => 'required|string|max:100',
            'shipping_zip' => 'required|string|max:20',
            'shipping_country' => 'required|string|max:100',
            // Payment info (mock)
            'card_number' => 'required|string',
            'card_expiry' => 'required|string|regex:/^\d{2}\/\d{2}$/',
            'card_cvc' => 'required|string|min:3|max:4',
            'card_name' => 'required|string|max:100',
            // Coupon (optional)
            'coupon_code' => 'nullable|string|max:50',
            // Cart items (REQUIRED for stateless/hybrid API)
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 2. Validate card with Luhn
        if (!$this->validateLuhn($request->input('card_number'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid card number',
                'errors' => ['card_number' => ['The card number is invalid.']],
            ], 422);
        }

        // 3. Process Cart Items from Payload
        // Since frontend is stateless/client-side cart, we trust the item IDs and quantities sent,
        // but we MUST re-fetch prices from DB to prevent tampering.
        
        $itemsPayload = $request->input('items', []);
        $productIds = collect($itemsPayload)->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $subtotal = 0;
        $orderItemsData = [];
        $stockErrors = [];

        foreach ($itemsPayload as $item) {
            $product = $products->get($item['product_id']);
            
            if (!$product) {
                // Should be caught by validation, but double check
                continue;
            }

            // Check stock
            if ($product->track_inventory && !$product->continue_selling_when_out_of_stock) {
                if ($product->stock_quantity < $item['quantity']) {
                    $stockErrors[] = "Insufficient stock for {$product->name}. Available: {$product->stock_quantity}";
                }
            }

            $itemTotal = $product->price * $item['quantity'];
            $subtotal += $itemTotal;

            $orderItemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => $product->price,
                'quantity' => $item['quantity'],
                'total' => $itemTotal,
            ];
        }

        if (!empty($stockErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Stock availability issue',
                'errors' => ['stock' => $stockErrors],
            ], 400);
        }

        // 4. Coupons
        $discountAmount = 0;
        $couponCode = $request->input('coupon_code');
        $coupon = null;

        if ($couponCode) {
            $coupon = Coupon::where('code', strtoupper($couponCode))->first();
            
            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid coupon code.',
                    'errors' => ['coupon_code' => ['Invalid coupon code.']],
                ], 400);
            }

            $validation = $coupon->isValid($subtotal);
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message'],
                    'errors' => ['coupon_code' => [$validation['message']]],
                ], 400);
            }

            $discountAmount = $coupon->calculateDiscount($subtotal);
        }

        // 5. Totals
        $shipping = $subtotal >= 150 ? 0 : 10;
        $tax = $subtotal * 0.08;
        $total = $subtotal + $shipping + $tax - $discountAmount;

        // 6. DB Transaction
        try {
            DB::beginTransaction();

            // Create Order
            // Use authenticated user ID if available, otherwise null (guest checkout)
            $userId = $request->user() ? $request->user()->id : null;

            // If no user from standard auth, try to resolve from custom UserSession token
            if (!$userId && $request->bearerToken()) {
                 $session = \App\Models\UserSession::where('session_token', $request->bearerToken())
                    ->where('expires_at', '>', now())
                    ->first();
                 if ($session) {
                     $userId = $session->user_id;
                 }
            }

            $order = Order::create([
                'user_id' => $userId, 
                'order_number' => Order::generateOrderNumber(),
                'status' => 'pending', // Hardcoded or use Order::STATUS_PENDING if constant exists
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total,
                'coupon_code' => $couponCode,
                'discount' => $discountAmount,
                'shipping_first_name' => $request->input('shipping_first_name'),
                'shipping_last_name' => $request->input('shipping_last_name'),
                'shipping_email' => $request->input('shipping_email'),
                'shipping_phone' => $request->input('shipping_phone'),
                'shipping_address1' => $request->input('shipping_address1'),
                'shipping_address2' => $request->input('shipping_address2'),
                'shipping_city' => $request->input('shipping_city'),
                'shipping_state' => $request->input('shipping_state'),
                'shipping_zip' => $request->input('shipping_zip'),
                'shipping_country' => $request->input('shipping_country'),
            ]);

            // Create Items & Update Stock
            foreach ($orderItemsData as $data) {
                $order->items()->create($data);

                $product = $products->get($data['product_id']);
                if ($product && $product->track_inventory) {
                    $product->decrement('stock_quantity', $data['quantity']);
                }
            }

            // Record Coupon Usage
            if ($coupon) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'discount_amount' => $discountAmount,
                    'created_at' => now(),
                ]);
                $coupon->incrementUsage();
            }

            DB::commit();

            // Clear Cart
            if ($userId) {
                $cart = \App\Models\Cart::where('user_id', $userId)->first();
                if ($cart) {
                    $cart->items()->delete();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process order.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    private function validateLuhn(string $cardNumber): bool
    {
        $cardNumber = preg_replace('/[\s-]/', '', $cardNumber);
        if (!ctype_digit($cardNumber)) return false;
        $length = strlen($cardNumber);
        if ($length < 13 || $length > 19) return false;
        $sum = 0;
        $alternate = false;
        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $cardNumber[$i];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) $digit -= 9;
            }
            $sum += $digit;
            $alternate = !$alternate;
        }
        return ($sum % 10) === 0;
    }
}
