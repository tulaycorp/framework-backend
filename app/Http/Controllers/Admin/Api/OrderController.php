<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     * Cached per unique query for 2 minutes.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'admin.orders.' . md5(json_encode($request->all()));
        
        $result = cache()->remember($cacheKey, 120, function () use ($request) {
            $query = Order::with('user')->withCount('items');
            
            // Search by order number or customer name
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhere('shipping_first_name', 'like', "%{$search}%")
                      ->orWhere('shipping_last_name', 'like', "%{$search}%")
                      ->orWhere('shipping_email', 'like', "%{$search}%");
                });
            }
            
            // Filter by status
            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            
            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);
            
            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $orders = $query->paginate($perPage);
            
            return [
                'data' => $orders->items(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'status' => 'in:pending,processing,shipped,delivered,cancelled',
            'shipping_first_name' => 'required|string|max:255',
            'shipping_last_name' => 'required|string|max:255',
            'shipping_email' => 'required|email|max:255',
            'shipping_phone' => 'nullable|string|max:50',
            'shipping_address1' => 'required|string',
            'shipping_address2' => 'nullable|string',
            'shipping_city' => 'required|string|max:255',
            'shipping_state' => 'nullable|string|max:255',
            'shipping_zip' => 'required|string|max:20',
            'shipping_country' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        
        $validated['order_number'] = Order::generateOrderNumber();
        $validated['status'] = $validated['status'] ?? Order::STATUS_PENDING;
        
        $order = Order::create($validated);
        
        // Add items
        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $product = \App\Models\Product::find($item['product_id']);
            $total = $product->price * $item['quantity'];
            $subtotal += $total;
            
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => $product->price,
                'quantity' => $item['quantity'],
                'total' => $total,
            ]);
        }
        
        // Calculate totals
        $tax = $subtotal * 0.1; // 10% tax
        $shipping = 10; // Flat rate
        $order->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $subtotal + $tax + $shipping,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order->load('items')->toApiArray(),
        ], 201);
    }

    /**
     * Display the specified order.
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['user', 'items'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => array_merge($order->toApiArray(), [
                'user' => $order->user?->toApiArray(),
                'items' => $order->items->map(fn($item) => $item->toApiArray()),
                'shipping_address' => [
                    'first_name' => $order->shipping_first_name,
                    'last_name' => $order->shipping_last_name,
                    'email' => $order->shipping_email,
                    'phone' => $order->shipping_phone,
                    'address1' => $order->shipping_address1,
                    'address2' => $order->shipping_address2,
                    'city' => $order->shipping_city,
                    'state' => $order->shipping_state,
                    'zip' => $order->shipping_zip,
                    'country' => $order->shipping_country,
                ],
            ]),
        ]);
    }

    /**
     * Update the specified order.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,processing,shipped,delivered,cancelled',
            'notes' => 'nullable|string',
            'shipping_first_name' => 'sometimes|string|max:255',
            'shipping_last_name' => 'sometimes|string|max:255',
            'shipping_email' => 'sometimes|email|max:255',
            'shipping_phone' => 'nullable|string|max:50',
            'shipping_address1' => 'sometimes|string',
            'shipping_address2' => 'nullable|string',
            'shipping_city' => 'sometimes|string|max:255',
            'shipping_state' => 'nullable|string|max:255',
            'shipping_zip' => 'sometimes|string|max:20',
            'shipping_country' => 'sometimes|string|max:255',
        ]);
        
        $order->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->fresh()->load('items')->toApiArray(),
        ]);
    }

    /**
     * Remove the specified order.
     */
    public function destroy(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    /**
     * Get order statuses.
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Order::statuses(),
        ]);
    }
}
