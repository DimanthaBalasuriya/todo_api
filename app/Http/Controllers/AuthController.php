<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UserAll;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    //
    
    public function register(RegisterRequest $request) {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function login(LoginRequest $request) {
       $credentials = $request->only('email', 'password');

       if(!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid email or password'
            ], 401);
       }

        return response()->json([
            'message' => 'User logged in successfully',
            'token' => $token
        ], 200);
    }

    public function users(UserAll $request) {
        $users = User::all();

        return response()->json([
            'message' => 'All users',
            'users' => $users
        ], 200);
    }

    public function profile(){

        $user = JWTAuth::parseToken()->authenticate();
        
        return response()->json([
            'message' => 'User profile',
            'user' => $user
        ], 200);
    }


}
