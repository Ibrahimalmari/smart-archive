<?php

namespace App\Http\DTOs\Auth;

class DeleteUserDto
{
    public function __construct(
        public int $userId,
    ) {}
}
