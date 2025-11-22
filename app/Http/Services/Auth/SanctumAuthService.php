<?php

namespace App\Http\Services\Auth;

use App\Http\DTOs\Auth\RegisterDto;
use App\Http\DTOs\Auth\LoginDto;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Repositories\UserRepositoryInterface;

class SanctumAuthService implements AuthServiceInterface
{
    private UserRepositoryInterface $users;

    public function __construct(UserRepositoryInterface $users)
    {
        $this->users = $users;
    }

   public function register(RegisterDto $dto)
{
    $data = [
        'name'     => $dto->name,
        'email'    => $dto->email,
        'password' => Hash::make($dto->password),
        'role'     => $dto->role
    ];

    // 1) إنشاء المستخدم
    $user = $this->users->create($data);

    // 2) إرسال إيميل التحقق
    $user->sendEmailVerificationNotification();

    // 3) إنشاء Token (إختياري)
    $token = $user->createToken('auth_token')->plainTextToken;

    return [
        'message' => 'User created. Verification email sent.',
        'user'    => $user,
        'token'   => $token,
    ];
}


 public function login(LoginDto $dto)
{
    if (!Auth::attempt([
        'email' => $dto->email,
        'password' => $dto->password
    ])) {
        return null;
    }

    /** @var \App\Models\User $user */
    $user = Auth::user();

    if (!$user) {
        return null;
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return [
        'user' => $user,
        'token' => $token
    ];
}

}
