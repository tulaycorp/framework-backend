<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional Bearer token authentication for web routes.
 * 
 * - If no token: continue as guest (cart works via cookie)
 * - If valid token: set auth_user_id on request attributes
 * - If invalid/expired token: return 401 (so frontend can clear stale token)
 */
class WebTokenAuthOptional
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        // No token = guest mode, continue normally
        if (!$token) {
            \Log::info('WebTokenAuthOptional: No Bearer token - continuing as guest', [
                'path' => $request->path(),
                'method' => $request->method()
            ]);
            return $next($request);
        }
        
        // Token provided - validate it
        $session = UserSession::where('session_token', $token)->first();
        
        if (!$session) {
            \Log::warning('WebTokenAuthOptional: Token NOT FOUND in database', [
                'token_prefix' => substr($token, 0, 10),
                'path' => $request->path()
            ]);
            $response = $next($request);
            if ($response instanceof \Illuminate\Http\JsonResponse || $response instanceof \Illuminate\Http\Response) {
                $response->header('X-Auth-Token-Status', 'Invalid');
            }
            return $response;
        }

        if ($session->expires_at <= now()) {
             \Log::warning('WebTokenAuthOptional: Token EXPIRED', [
                'token_prefix' => substr($token, 0, 10),
                'expires_at' => $session->expires_at,
                'now' => now(),
                'path' => $request->path()
            ]);
            $response = $next($request);
            if ($response instanceof \Illuminate\Http\JsonResponse || $response instanceof \Illuminate\Http\Response) {
                $response->header('X-Auth-Token-Status', 'Expired');
            }
            return $response;
        }
        
        // Valid token - attach user ID to request for downstream use
        $request->attributes->set('auth_user_id', $session->user_id);
        \Log::info('WebTokenAuthOptional: Token valid - authenticated as user', [
            'user_id' => $session->user_id,
            'path' => $request->path(),
            'method' => $request->method()
        ]);
        
        return $next($request);
    }
}
