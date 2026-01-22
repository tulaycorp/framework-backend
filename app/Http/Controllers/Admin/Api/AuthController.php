<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login admin user.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        
        $admin = \App\Models\Admin::where('email', $validated['email'])->first();
        
        if (!$admin || !Hash::check($validated['password'], $admin->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }
        
        // Create session token
        $session = Session::createForAdmin($admin);
        
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $admin->toApiArray(),
                'token' => $session->session_token,
                'expires_at' => $session->expires_at->toISOString(),
            ],
        ]);
    }

    /**
     * Logout admin user.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        
        if ($token) {
            Session::where('session_token', $token)->delete();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user info.
     */
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }
        
        $session = Session::where('session_token', $token)
            ->where('expires_at', '>', now())
            ->first();
        
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }
        
        $admin = $session->admin;
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $admin->toApiArray(),
        ]);
    }
}
