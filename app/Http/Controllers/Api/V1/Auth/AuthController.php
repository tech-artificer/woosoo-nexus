<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
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
                    'api-token', 
                    [
                        'user'
                ])->plainTextToken
            ]);
        }

        return response()->json([
            'user' => 'unauthenticated'
        ]);
    }
}
