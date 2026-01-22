<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    /**
     * Validate a coupon code.
     */
    /**
     * Validate a coupon code.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find coupon (case-insensitive)
        $code = strtoupper($request->input('code'));
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code.',
            ], 404);
        }

        // Validate coupon
        $subtotal = (float) $request->input('subtotal');
        $validation = $coupon->isValid($subtotal);

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['message'],
            ], 400);
        }

        // Calculate discount
        $discountAmount = $coupon->calculateDiscount($subtotal);

        return response()->json([
            'success' => true,
            'message' => 'Coupon is valid',
            'coupon' => [
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
                'discount_amount' => $discountAmount,
                'description' => $coupon->description,
            ],
        ]);
    }
}
