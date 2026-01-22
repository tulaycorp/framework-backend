<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CartMergeService - Handles merging guest cart into user cart on login.
 */
class CartMergeService
{
    /**
     * Merge guest cart items into user cart.
     * 
     * @param string|null $guestId The guest session ID (from cookie)
     * @param int $userId The authenticated user's ID
     */
    public function mergeGuestIntoUser(?string $guestId, int $userId): void
    {
        Log::info('CartMergeService: Attempting merge', [
            'guest_id' => $guestId,
            'user_id' => $userId,
        ]);

        // No guest ID = nothing to merge
        if (empty($guestId)) {
            Log::info('CartMergeService: No guest ID provided, skipping merge');
            return;
        }
        
        DB::transaction(function () use ($guestId, $userId) {
            // Find guest cart
            $guestCart = Cart::where('session_id', $guestId)->first();
            
            if (!$guestCart) {
                Log::info('CartMergeService: No guest cart found for session_id', ['session_id' => $guestId]);
                // List all carts for debugging
                $allCarts = Cart::all(['id', 'user_id', 'session_id'])->toArray();
                Log::info('CartMergeService: All carts in DB', ['carts' => $allCarts]);
                return;
            }
            
            Log::info('CartMergeService: Found guest cart', [
                'cart_id' => $guestCart->id,
                'items_count' => $guestCart->items()->count(),
            ]);
            
            // Get or create user cart
            $userCart = Cart::firstOrCreate(['user_id' => $userId]);
            
            Log::info('CartMergeService: User cart', [
                'cart_id' => $userCart->id,
                'existing_items' => $userCart->items()->count(),
            ]);
            
            // Merge each guest item into user cart
            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()
                    ->where('product_id', $guestItem->product_id)
                    ->first();
                
                if ($existingItem) {
                    // User already has this product - increment quantity
                    $existingItem->increment('quantity', $guestItem->quantity);
                    Log::info('CartMergeService: Incremented quantity', [
                        'product_id' => $guestItem->product_id,
                        'added_qty' => $guestItem->quantity,
                    ]);
                } else {
                    // User doesn't have this product - create new item
                    $userCart->items()->create([
                        'product_id' => $guestItem->product_id,
                        'quantity' => $guestItem->quantity,
                    ]);
                    Log::info('CartMergeService: Added new item', [
                        'product_id' => $guestItem->product_id,
                        'qty' => $guestItem->quantity,
                    ]);
                }
            }
            
            // Delete guest cart (cascade deletes items)
            $guestCart->delete();
            Log::info('CartMergeService: Deleted guest cart after merge');
        });
    }
}
