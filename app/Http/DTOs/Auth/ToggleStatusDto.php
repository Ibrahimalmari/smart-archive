<?php

namespace App\Http\DTOs\Auth;

class ToggleStatusDto
{
    public function __construct(
        public string $status
    ) {}
}
