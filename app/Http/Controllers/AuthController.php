<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\Auth\AuthServiceInterface;
use App\Http\DTOs\Auth\RegisterDto;
use App\Http\DTOs\Auth\LoginDto;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    private AuthServiceInterface $auth;

    public function __construct(AuthServiceInterface $auth)
    {
        $this->auth = $auth;
    }

    // Register
      public function register(RegisterRequest $request)
    {
        $dto = new RegisterDto(
            $request->name,
            $request->email,
            $request->password,
            $request->role ?? 'Employee'
        );


        return response()->json(
            $this->auth->register($dto),
            201
        );
    }

    // Login
     public function login(LoginRequest $request)
    {
        $dto = new LoginDto(
            $request->email,
            $request->password
        );

        $result = $this->auth->login($dto);

        if (!$result) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json($result);
    }

    // Get logged in user
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
