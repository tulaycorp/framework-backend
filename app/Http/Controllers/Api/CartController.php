<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected CartService $cartService;
    
    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Get cart data.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getActiveCart($request);
        
        \Log::info('CartController::index - Returning cart', [
            'cart_id' => $cart->id,
            'user_id' => $cart->user_id,
            'session_id' => $cart->session_id,
            'items_count' => $cart->items()->count()
        ]);
        
        // Eager load items and products
        // Assuming relationship is defined in Cart model as 'items' and then 'product'
        $cart->load('items.product');

        $items = $cart->items->map(function($i) {
            return ['product_id' => $i->product_id, 'quantity' => $i->quantity];
        });
        
        \Log::info('CartController::index - Items being returned', ['items' => $items->toArray()]);

        $response = response()->json(['items' => $items]);
        
        // Attach guest session cookie if not authenticated
        $guestId = $this->cartService->getCurrentGuestId($request);
        if ($guestId) {
            $response = $response->cookie('eshop_session_id', $guestId, 60 * 24 * 30, '/', null, false, false, false, 'lax');
            \Log::info('CartController::index - Attaching cookie to response', ['guest_id' => $guestId]);
        }
        
        return $response;
    }

    /**
     * Sync cart items from frontend.
     * This is a full sync - items not in the request will be removed.
     */
    public function sync(Request $request): JsonResponse
    {
        $cart = $this->cartService->getActiveCart($request);
        $localItems = $request->input('cart', []);
        
        \Log::info('CartController::sync - Starting sync', [
            'cart_id' => $cart->id,
            'user_id' => $cart->user_id,
            'session_id' => $cart->session_id,
            'current_items_in_db' => $cart->items()->count(),
            'incoming_items_count' => count($localItems),
            'incoming_items' => $localItems
        ]);
        
        // Get product IDs from the incoming cart
        $incomingProductIds = collect($localItems)->pluck('id')->map(fn($id) => (string) $id)->toArray();
        
        \Log::info('CartController::sync - Processing deletions', [
            'incoming_product_ids' => $incomingProductIds,
            'items_to_delete_count' => $cart->items()->whereNotIn('product_id', $incomingProductIds)->count()
        ]);
        
        // Delete items not in the incoming cart (handles removals)
        $deletedCount = $cart->items()->whereNotIn('product_id', $incomingProductIds)->delete();
        
        \Log::info('CartController::sync - Deleted items', ['count' => $deletedCount]);

        // Update or create items from frontend
        foreach ($localItems as $item) {
            $productId = $item['id'];
            $quantity = $item['qty'] ?? 1;
            
            \Log::info('CartController::sync - Processing item', [
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
            
            $existing = $cart->items()->where('product_id', $productId)->first();
            
            if ($existing) {
                // Update quantity (client is authoritative for sync)
                \Log::info('CartController::sync - Updating existing item', [
                    'item_id' => $existing->id,
                    'old_quantity' => $existing->quantity,
                    'new_quantity' => $quantity
                ]);
                $existing->quantity = $quantity;
                $existing->save();
            } else {
                // Create new item
                \Log::info('CartController::sync - Creating new item', [
                    'cart_id' => $cart->id,
                    'product_id' => $productId,
                    'quantity' => $quantity
                ]);
                $newItem = $cart->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity
                ]);
                \Log::info('CartController::sync - Item created', ['item_id' => $newItem->id]);
            }
        }
        
        $finalCount = $cart->fresh()->items()->count();
        \Log::info('CartController::sync - Sync complete', [
            'final_items_count' => $finalCount,
            'final_items' => $cart->fresh()->items()->get(['id', 'product_id', 'quantity'])->toArray()
        ]);
        
        $response = response()->json([
            'success' => true, 
            'cart' => $cart->fresh()->load('items.product')->items->map(function($i) {
                return ['id' => $i->product_id, 'qty' => $i->quantity];
            })
        ]);
        
        // Attach guest session cookie if not authenticated
        $guestId = $this->cartService->getCurrentGuestId($request);
        if ($guestId) {
            $response = $response->cookie('eshop_session_id', $guestId, 60 * 24 * 30, '/', null, false, false, false, 'lax');
            \Log::info('CartController::sync - Attaching cookie to response', ['guest_id' => $guestId]);
        }
        
        return $response;
    }
    
    /**
     * Reset guest session - called on logout to give user a fresh cart.
     */
    public function resetGuest(Request $request): JsonResponse
    {
        $newGuestId = $this->cartService->resetGuestSession($request);
        
        return response()->json([
            'success' => true,
            'message' => 'Guest session reset',
            'new_session_id' => $newGuestId
        ]);
    }
}
