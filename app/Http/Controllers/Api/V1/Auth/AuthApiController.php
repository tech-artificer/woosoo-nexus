<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException; // Import for validation errors

class AuthApiController extends Controller
{
    /**
     * Authenticate a user
     * 
     * @unauthenticated
     * 
     */
    public function authenticate(Request $request) {

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if( Auth::attempt($credentials)) {

            $user = Auth::user();
            return response()->json([
                'user' => $user, 
                'token' => $user->createToken(
                    'auth_token', 
                    [
                        'user'
                ])->plainTextToken
            ]);
        }

        return response()->json([
            'user' => 'unauthenticated'
        ]);
    }

    /**
     * Create a new token for the authenticated user
     * 
     * @unauthenticated
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createToken(Request $request)
    {

        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'], // A name for the device/client (e.g., 'web browser', 'mobile app')
        ]);

        // // Attempt to authenticate the user
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = Auth::user();

        // Create a new token for the authenticated user
        // The token name helps you identify where the token was issued.

        if(  $user->tokens()->where('name', 'auth_token')->exists()) {
            $user->tokens()->where('name', 'auth_token')->delete();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }

    public function revokeToken(Request $request)
    {
        // Revoke the current token
        // $request->user()->currentAccessToken()->delete();

        // Or revoke all tokens for the user
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Token revoked successfully']);
    }
}
