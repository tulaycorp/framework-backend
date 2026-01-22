<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession as Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\CartMergeService;

class UserController extends Controller
{
    /**
     * Handle user login.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Email and password are required'], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['error' => 'Invalid email or password'], 401);
        }

        // Merge guest cart into user cart (if guest had items)
        // We get session_id from request body OR cookie directly (since we disabled encryption for it)
        $guestId = $request->input('guest_session_id') ?? $request->cookie('eshop_session_id');
        
        \Log::info('UserController login: Attempting merge', [
            'guest_id_from_input' => $request->input('guest_session_id'),
            'guest_id_from_cookie' => $request->cookie('eshop_session_id'),
            'all_cookies' => $request->cookies->all(),
            'final_guest_id' => $guestId
        ]);
        if ($guestId) {
            app(CartMergeService::class)->mergeGuestIntoUser($guestId, $user->id);
        }

        // Create session token
        $session = Session::createForUser($user);

        return response()->json([
            'success' => true,
            'user' => array_merge($user->toApiArray(), [
                'session_token' => $session->session_token,
            ]),
            'expires_at' => $session->expires_at->toDateTimeString(),
        ]);
    }

    /**
     * Handle user signup.
     */
    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'password' => 'required|string|min:6',
            'address1' => 'required|string|max:100',
            'address2' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Required fields are missing'], 400);
        }

        // Check if email already exists
        if (User::where('email', $request->email)->exists()) {
            return response()->json(['error' => 'Email already registered'], 409);
        }

        // Create user
        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'address1' => $request->address1,
            'address2' => $request->address2,
            'country_code' => $request->country_code ?? '+64',
            'phone' => $request->phone,
            'role' => 'user',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully',
        ]);
    }

    /**
     * Check if email exists.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $email = $request->query('email', '');

        if (empty($email)) {
            return response()->json(['error' => 'Email is required'], 400);
        }

        $exists = User::where('email', $email)->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $sessionToken = $request->input('session_token', '');

        if (empty($sessionToken)) {
            return response()->json(['error' => 'Session token required'], 400);
        }

        Session::where('session_token', $sessionToken)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get user profile data for autofill.
     */
    public function profile(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken() ?? $request->input('session_token');

        if (empty($sessionToken)) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $session = Session::where('session_token', $sessionToken)
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Invalid or expired session'], 401);
        }

        $user = User::find($session->user_id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'address1' => $user->address1,
                'address2' => $user->address2,
                'country_code' => $user->country_code,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Update user profile data.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken() ?? $request->input('session_token');

        if (empty($sessionToken)) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $session = Session::where('session_token', $sessionToken)
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Invalid or expired session'], 401);
        }

        $user = User::find($session->user_id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:50',
            'middle_name' => 'sometimes|nullable|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'address1' => 'sometimes|required|string|max:100',
            'address2' => 'sometimes|nullable|string|max:100',
            'country_code' => 'sometimes|nullable|string|max:10',
            'phone' => 'sometimes|nullable|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'details' => $validator->errors()], 400);
        }

        // Update only the fields that are provided
        $updateFields = ['first_name', 'middle_name', 'last_name', 'address1', 'address2', 'country_code', 'phone'];
        foreach ($updateFields as $field) {
            if ($request->has($field)) {
                $user->$field = $request->input($field);
            }
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'address1' => $user->address1,
                'address2' => $user->address2,
                'country_code' => $user->country_code,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken() ?? $request->input('session_token');

        if (empty($sessionToken)) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $session = Session::where('session_token', $sessionToken)
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Invalid or expired session'], 401);
        }

        $user = User::find($session->user_id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'details' => $validator->errors()], 400);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json(['error' => 'Current password is incorrect'], 400);
        }

        // Update password
        $user->password_hash = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }
}
