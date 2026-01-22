<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * CartService - Single responsibility for cart identification and resolution.
 */
class CartService
{
    /**
     * Ensure guest session cookie exists, return the session ID.
     */
    public function ensureGuestIdCookie(Request $request): string
    {
        $guestId = $request->cookie('eshop_session_id');
        
        \Log::info('CartService::ensureGuestIdCookie - Called', [
            'existing_cookie' => $guestId,
            'all_cookies' => $request->cookies->all()
        ]);
        
        // Robustness: Ensure the ID is a valid UUID.
        // If it's not (e.g. it's an old encrypted string "eyJ..."), generate a new one.
        if (!$guestId || !Str::isUuid($guestId)) {
            $guestId = Str::uuid()->toString();
            Cookie::queue('eshop_session_id', $guestId, 60 * 24 * 30, '/', null, false, false); // 30 days, not HttpOnly, not Secure
            \Log::info('CartService::ensureGuestIdCookie - Generated new cookie', ['new_id' => $guestId]);
        }
        
        return $guestId;
    }
    
    /**
     * Get the current guest ID from the request (for cookie attachment).
     */
    public function getCurrentGuestId(Request $request): ?string
    {
        $userId = $request->attributes->get('auth_user_id');
        if ($userId) {
            return null; // User is authenticated, no guest ID needed
        }
        
        $guestId = $request->cookie('eshop_session_id');
        if (!$guestId || !Str::isUuid($guestId)) {
            return $this->ensureGuestIdCookie($request);
        }
        
        return $guestId;
    }
    
    /**
     * Get the active cart for the current request.
     * 
     * If user is authenticated (via middleware), return user's cart.
     * Otherwise, return guest cart based on cookie session.
     */
    public function getActiveCart(Request $request): Cart
    {
        $userId = $request->attributes->get('auth_user_id');
        
        if ($userId) {
            \Log::info('CartService::getActiveCart - User authenticated', ['user_id' => $userId]);
            // Authenticated user - get or create user cart
            $cart = Cart::firstOrCreate(['user_id' => $userId]);
            \Log::info('CartService::getActiveCart - User cart retrieved', [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'session_id' => $cart->session_id,
                'items_count' => $cart->items()->count()
            ]);
            return $cart;
        }
        
        // Guest - get or create cart by session cookie
        $guestId = $this->ensureGuestIdCookie($request);
        \Log::info('CartService::getActiveCart - Guest mode', ['session_id' => $guestId]);
        $cart = Cart::firstOrCreate(['session_id' => $guestId]);
        \Log::info('CartService::getActiveCart - Guest cart retrieved', [
            'cart_id' => $cart->id,
            'user_id' => $cart->user_id,
            'session_id' => $cart->session_id,
            'items_count' => $cart->items()->count()
        ]);
        return $cart;
    }
    
    /**
     * Rotate guest session cookie and optionally delete old guest cart.
     * Called on logout to ensure fresh guest cart.
     * 
     * IMPORTANT: Only deletes GUEST carts (session_id), never USER carts (user_id).
     * User carts should persist indefinitely so items are saved when they log back in.
     */
    public function resetGuestSession(Request $request): string
    {
        $oldGuestId = $request->cookie('eshop_session_id');
        
        \Log::info('CartService::resetGuestSession - Starting', ['old_guest_id' => $oldGuestId]);
        
        // Delete old GUEST cart ONLY if it exists (not user carts)
        // User carts (with user_id set) should never be deleted on logout
        if ($oldGuestId) {
            // First, log what we're about to delete
            $cartsToDelete = Cart::where('session_id', $oldGuestId)
                ->whereNull('user_id')
                ->get(['id', 'user_id', 'session_id']);
            
            \Log::info('CartService::resetGuestSession - Carts to delete', [
                'count' => $cartsToDelete->count(),
                'carts' => $cartsToDelete->toArray()
            ]);
            
            $deletedCount = Cart::where('session_id', $oldGuestId)
                ->whereNull('user_id')
                ->delete();
                
            \Log::info('CartService::resetGuestSession - Deleted carts', ['count' => $deletedCount]);
        }
        
        // Generate new session ID and queue cookie
        $newGuestId = Str::uuid()->toString();
        Cookie::queue('eshop_session_id', $newGuestId, 60 * 24 * 30, null, null, null, false); // 30 days, not HttpOnly
        
        \Log::info('CartService::resetGuestSession - Generated new session', ['new_guest_id' => $newGuestId]);
        
        return $newGuestId;
    }
}
