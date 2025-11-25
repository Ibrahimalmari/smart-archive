<?php

namespace App\Http\Services\Auth;

use App\Http\DTOs\Auth\ForgotPasswordDto;
use App\Http\DTOs\Auth\ResetPasswordDto;

interface PasswordServiceInterface
{
    /**
     * إرسال إيميل إعادة تعيين كلمة المرور
     */
    public function forgot(ForgotPasswordDto $dto): bool;

    /**
     * تنفيذ إعادة تعيين كلمة المرور
     */
    public function reset(ResetPasswordDto $dto): bool;
}
