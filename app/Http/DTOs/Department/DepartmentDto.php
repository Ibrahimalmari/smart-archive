<?php

namespace App\Http\DTOs\Department;

class DepartmentDto
{
    public ?int $organizationId;
    public ?string $name;
    public ?string $code;
    public ?string $status;

    public function __construct(
        ?int $organizationId = null,
        ?string $name = null,
        ?string $code = null,
        ?string $status = null,
    ) {
        $this->organizationId = $organizationId;
        $this->name           = $name;
        $this->code           = $code;
        $this->status         = $status;
    }
}
