<?php

namespace App\Http\Services\Auth;

use App\Http\DTOs\Auth\RegisterDto;
use App\Http\DTOs\Auth\LoginDto;

interface AuthServiceInterface
{
    public function register(RegisterDto $dto);
    public function login(LoginDto $dto);
}
