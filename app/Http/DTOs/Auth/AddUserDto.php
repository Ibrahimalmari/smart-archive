<?php

namespace App\Http\DTOs\Auth;

class AddUserDto
{
    public string $name;
    public string $email;
    public string $password;
    public string $role;
    public ?int $organization_id;
    public ?int $department_id;

    public function __construct(
        string $name,
        string $email,
        string $password,
        string $role = 'Employee',
        ?int $organization_id = null,
        ?int $department_id = null,
    ) {
        $this->name            = $name;
        $this->email           = $email;
        $this->password        = $password;
        $this->role            = $role;
        $this->organization_id = $organization_id;
        $this->department_id   = $department_id;
    }
}
