<?php

namespace App\Http\DTOs\Auth;

class UpdateUserDto
{
    public ?string $name;
    public ?string $email;
    public ?string $password;
    public ?string $role;

    public function __construct($name = null, $email = null, $password = null, $role = null)
    {
        $this->name     = $name;
        $this->email    = $email;
        $this->password = $password;
        $this->role     = $role;
    }
}
