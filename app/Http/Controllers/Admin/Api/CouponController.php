<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    /**
     * List all coupons.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        // Search filter
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $coupons = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'coupons' => $coupons->items(),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get a single coupon.
     */
    public function show($id): JsonResponse
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'coupon' => $coupon,
        ]);
    }

    /**
     * Create a new coupon.
     */
    public function store(Request $request): JsonResponse
    {
        $messages = [
            'code.unique' => 'This coupon code already exists.',
            'discount_value.gt' => 'Discount value must be greater than 0.',
            'expires_at.after' => 'Expiration date must be after the start date.',
        ];

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:coupons,code',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|gt:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $coupon = Coupon::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'coupon' => $coupon,
        ], 201);
    }

    /**
     * Update a coupon.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found',
            ], 404);
        }

        $messages = [
            'code.unique' => 'This coupon code already exists.',
            'discount_value.gt' => 'Discount value must be greater than 0.',
            'expires_at.after' => 'Expiration date must be after the start date.',
        ];

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:50|unique:coupons,code,' . $id,
            'description' => 'nullable|string',
            'discount_type' => 'sometimes|required|in:percentage,fixed',
            'discount_value' => 'sometimes|required|numeric|gt:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $coupon->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'coupon' => $coupon,
        ]);
    }

    /**
     * Delete a coupon.
     */
    public function destroy($id): JsonResponse
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found',
            ], 404);
        }

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully',
        ]);
    }

    /**
     * Get coupon usage details.
     */
    public function usages($id): JsonResponse
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found',
            ], 404);
        }

        $usages = CouponUsage::where('coupon_id', $id)
            ->with(['order', 'user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($usage) {
                return [
                    'id' => $usage->id,
                    'order_number' => $usage->order->order_number ?? 'N/A',
                    'user_name' => $usage->user 
                        ? trim($usage->user->first_name . ' ' . $usage->user->last_name)
                        : ($usage->order->shipping_first_name ?? 'Guest') . ' ' . ($usage->order->shipping_last_name ?? ''),
                    'user_email' => $usage->user->email ?? $usage->order->shipping_email ?? 'N/A',
                    'discount_amount' => $usage->discount_amount,
                    'created_at' => $usage->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'usages' => $usages,
            'total_usages' => $usages->count(),
        ]);
    }
}
