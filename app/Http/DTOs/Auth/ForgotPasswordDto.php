<?php

namespace App\Http\DTOs\Auth;

class ForgotPasswordDto
{
    public string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }
}
