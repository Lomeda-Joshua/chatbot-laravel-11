<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class SsoController extends Controller
{
    /**
     * Generate SSO token for authenticated user
     */
    public function generateSsoToken(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $expiryMinutes = $this->getTokenExpiryMinutes($user);
        
        if (config('sso.auto_cleanup_expired')) {
            $user->tokens()->where('expires_at', '<', now())->delete();
        }
        
        $maxTokens = config('sso.security.max_tokens_per_user');
        if ($maxTokens && $user->tokens()->count() >= $maxTokens) {
            $user->tokens()->oldest()->first()?->delete();
        }

        $token = $user->createToken(config('sso.token_name'), ['*'], now()->addMinutes($expiryMinutes))->plainTextToken;
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'expires_in_minutes' => $expiryMinutes,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'first_name' => $user->first_name ?? '',           
                'last_name' => $user->last_name ?? '',
                'middle_name' => $user->middle_name ?? '',
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at,
            ],
            'redirect_url' => $request->input('redirect_url'),
        ]);
    }

    /**
     * Verify SSO token and authenticate user
     */
    public function verifySsoToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Token is required'], 400);
        }

        $accessToken = PersonalAccessToken::findToken($request->token);
        
        if (!$accessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return response()->json(['error' => 'Token expired'], 401);
        }

        $user = $accessToken->tokenable;
        
        if (!$user || !$user->is_active) {
            return response()->json(['error' => 'User not found or inactive'], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'middle_name' => $user->middle_name ?? '',
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at,
            ]
        ]);
    }

    /**
     * Login via API and return token
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json(['error' => 'Email not verified'], 403);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Account is inactive'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'middle_name' => $user->middle_name ?? '',
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at,
            ]
        ]);
    }

    /**
     * Get authenticated user information
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }

    /**
     * Logout and revoke token
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }

    /**
     * SSO Logout - Revoke specific token
     */
    public function ssoLogout(Request $request)
    {
        $token = $request->input('token');
        
        if (!$token) {
            return response()->json(['error' => 'Token is required'], 400);
        }
        
        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken) {
            $user = $accessToken->tokenable;
            
            $accessToken->delete();
            
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            
            Log::info('SSO logout completed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'SSO logout successful'
        ]);
    }

    /**
     * Force logout endpoint used by Unified master logout
     */
    public function forceLogout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'user_email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
            ], 400);
        }

        $user = null;

        if ($request->user_id) {
            $user = User::find($request->user_id);
        } elseif ($request->user_email) {
            $user = User::where('email', $request->user_email)->first();
        }

        if ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();

            Log::info('Force logout completed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Force logout successful',
        ], 200);
    }

    /**
     * Get token expiry minutes
     */
    protected function getTokenExpiryMinutes($user)
    {
        return config('sso.session.token_expiry', 1800) / 60;
    }
}
