<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\UserSession as Session; // Assuming shared model or consistent logic if we need to manually validating token again? 
// Wait, if this is backend, we can trust the middleware? 
// NO, backend api.php typically uses auth:sanctum or custom middleware. 
// Looking at api.php, it uses a mix. We will implement basic retrieval for now.

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * List orders for a user.
     */
    public function list(Request $request, $userId): JsonResponse
    {
        // Security check: Ensure the requesting user matches the requested userId
        // Note: In real app, we should use Auth::id() instead of passing userId in URL 
        // OR verify the token belongs to userId.
        
        // Extract token
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Authentication required'], 401);
        }
        
        // Validate token against UserSession (since backend uses this)
        $session = Session::where('session_token', $token)
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$session) {
             return response()->json(['error' => 'Invalid or expired session'], 401);
        }
        
        if ($session->user_id != $userId) {
             return response()->json(['error' => 'Unauthorized access to these orders'], 403);
        }

        $orders = Order::where('user_id', $userId)
            ->with(['items']) // Load related items
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                // Ensure items are loaded before converting
                if (!$order->relationLoaded('items')) {
                    $order->load('items'); 
                }
                return $order->toApiArray();
            });

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }
}
