<?php

namespace App\Http\Services\Auth;

use App\Http\DTOs\Auth\ForgotPasswordDto;
use App\Http\DTOs\Auth\ResetPasswordDto;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordService implements PasswordServiceInterface
{
    public function forgot(ForgotPasswordDto $dto): bool
    {
        // نحاول نلاقي المستخدم
        $user = User::where('email', $dto->email)->first();

        if (!$user) {
            return false; // الإيميل غير موجود
        }

        // Laravel Password يستخدم جدول password_reset_tokens
        $token = Password::createToken($user);

        // نرسل Notification مخصصة
        $user->notify(new ResetPasswordNotification($token));

        return true;
    }

    public function reset(ResetPasswordDto $dto): bool
    {
        $status = Password::reset(
            [
                'email'                 => $dto->email,
                'token'                 => $dto->token,
                'password'              => $dto->password,
                'password_confirmation' => $dto->password, // لو حاب تضيفها في الفاليديشن
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET;
    }
}
