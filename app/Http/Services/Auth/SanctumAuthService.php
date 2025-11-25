<?php

namespace App\Http\Services\Auth;

use App\Http\DTOs\Auth\AddUserDto;
use App\Http\DTOs\Auth\LoginDto;
use App\Http\DTOs\Auth\UpdateUserDto;
use App\Http\DTOs\Auth\ToggleStatusDto;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Repositories\UserRepositoryInterface;

class SanctumAuthService implements AuthServiceInterface
{
    private UserRepositoryInterface $users;

    public function __construct(UserRepositoryInterface $users)
    {
        $this->users = $users;
    }

   public function AddUser(AddUserDto $dto)
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

 

    return [
        'message' => 'User created. Verification email sent.',
        'user'    => $user,
       
    ];
}


 public function login(LoginDto $dto): array|null
{
    if (!Auth::attempt([
        'email' => $dto->email,
        'password' => $dto->password,
    ])) {
        return null; // خطأ كلمة سر أو إيميل
    }

    /** @var \App\Models\User $user */
    $user = Auth::user();

    // ⛔️ الإيميل غير مُتحقّق → نمنع تسجيل الدخول
    if (!$user->hasVerifiedEmail()) {
        return [
            'error'   => true,
            'message' => 'Email not verified. Please verify your email before logging in.',
        ];
    }

    if ($user->status === 'inactive') {
    return ['error' => 'Account is suspended'];
    } 

            // حذف كل التوكينات القديمة
            $user->tokens()->delete();

        // abilities حسب الدور
    $abilities = match ($user->role) {
        'Admin'   => ['*'], // وصول كامل
        'Manager' => ['manage-users', 'view-users'],
        'Employee'=> ['view-users'],
        default   => [],
    };

            // abilities حسب الدور
        $abilities = match ($user->role) {
            'Admin'    => ['*'], // صلاحيات كاملة
            'Manager'  => ['manage-users', 'view-users'],
            'Employee' => ['view-users'],
            default    => [],
        };

        // إنشاء التوكن
        $token = $user->createToken(
            'auth_token',
            $abilities
        );

        // تحديد مدة الصلاحية (مثلاً ساعتين)
        $token->accessToken->expires_at = now()->addMinutes(120);
        $token->accessToken->save();
        

    return [
        'user'  => $user,
        'token' => $token
    ];
}


public function logoutAll(int $userId): void
{
    $user = User::findOrFail($userId);
    $user->tokens()->delete();
}



public function updateOwnProfile(int $userId, UpdateUserDto $dto)
{
    $data = [];

    $user = User::findOrFail($userId);

    // === Check name ===
    if ($dto->name !== null) {
        $data['name'] = $dto->name;
    }

    // === Check email ===
    if ($dto->email !== null && $dto->email !== $user->email) {

        $data['email'] = $dto->email;

        // ❗ Reset email verification status
        $data['email_verified_at'] = null;

        // ❗ Send new verification email
        $user->forceFill(['email' => $dto->email, 'email_verified_at' => null]);
        $user->save();
        $user->sendEmailVerificationNotification();
    }

    // === Check password ===
    if ($dto->password !== null) {
        $data['password'] = Hash::make($dto->password);
    }

    // ❌ المستخدم لا يمكنه تعديل الدور
    unset($data['role']);

    return $this->users->update($userId, $data);
}



public function updateUserAsAdmin(int $userId, UpdateUserDto $dto)
{
    $data = [];

    $user = User::findOrFail($userId);

    if ($dto->name !== null) {
        $data['name'] = $dto->name;
    }

    // === Email update + re-verification ===
    if ($dto->email !== null && $dto->email !== $user->email) {

        $data['email'] = $dto->email;

        // Reset verification
        $data['email_verified_at'] = null;

        // Update immediately and send verification
        $user->forceFill(['email' => $dto->email, 'email_verified_at' => null]);
        $user->save();
        $user->sendEmailVerificationNotification();
    }

    if ($dto->password !== null) {
        $data['password'] = Hash::make($dto->password);
    }

    // Admin/Manager can update role
    if ($dto->role !== null) {
        $data['role'] = $dto->role;
    }

    return $this->users->update($userId, $data);
}


    public function deleteUser(int $id)
    {
        
       $user = User::findOrFail($id);

            // حذف جميع التوكينات
            $user->tokens()->delete();

            // حذف الحساب نفسه
            return $user->delete();
                
    }

    public function toggleUserStatus(int $id, ToggleStatusDto $dto)
    {
        return $this->users->updateStatus($id, $dto->status);
    }




}
