<?php

namespace App\Http\Middleware;

use App\Models\Session;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminApiAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token required.',
            ], 401);
        }
        
        $session = Session::where('session_token', $token)
            ->where('expires_at', '>', now())
            ->first();
        
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }
        
        $admin = $session->admin;
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.',
            ], 403);
        }
        
        // Attach admin to request
        $request->merge(['auth_user' => $admin]);
        
        return $next($request);
    }
}
