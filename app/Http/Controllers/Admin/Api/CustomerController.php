<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     * Cached per unique query for 2 minutes.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'admin.customers.' . md5(json_encode($request->all()));
        
        $result = cache()->remember($cacheKey, 120, function () use ($request) {
            $query = User::where('role', '!=', 'admin');
            
            // Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
            
            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);
            
            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $customers = $query->paginate($perPage);
            
            return [
                'data' => collect($customers->items())->map(fn($user) => $user->toApiArray())->toArray(),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
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
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:50',
            'address1' => 'nullable|string',
            'address2' => 'nullable|string',
            'country_code' => 'nullable|string|max:10',
        ]);
        
        $validated['password_hash'] = Hash::make($validated['password']);
        $validated['role'] = 'customer';
        unset($validated['password']);
        
        $customer = User::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer->toApiArray(),
        ], 201);
    }

    /**
     * Display the specified customer.
     */
    public function show(int $id): JsonResponse
    {
        $customer = User::where('role', '!=', 'admin')
            ->with('orders')
            ->findOrFail($id);
        
        // Calculate stats from loaded orders
        $orders = $customer->orders;
        $totalSpent = $orders->sum('total');
        $lastOrder = $orders->sortByDesc('created_at')->first();
        $lastOrderDate = $lastOrder ? $lastOrder->created_at->format('M d, Y') : 'Never';
        
        return response()->json([
            'success' => true,
            'data' => array_merge($customer->toApiArray(), [
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
                'address1' => $customer->address1,
                'address2' => $customer->address2,
                'country_code' => $customer->country_code,
                'created_at' => $customer->created_at->toISOString(),
                'orders_count' => $orders->count(),
                'total_spent' => $totalSpent,
                'last_order_date' => $lastOrderDate,
            ]),
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customer = User::where('role', '!=', 'admin')->findOrFail($id);
        
        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id . '|max:255',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:50',
            'address1' => 'nullable|string',
            'address2' => 'nullable|string',
            'country_code' => 'nullable|string|max:10',
        ]);
        
        if (isset($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
            unset($validated['password']);
        }
        
        $customer->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer->fresh()->toApiArray(),
        ]);
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(int $id): JsonResponse
    {
        $customer = User::where('role', '!=', 'admin')->findOrFail($id);
        $customer->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }
}
