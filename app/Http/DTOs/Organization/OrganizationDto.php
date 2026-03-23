<?php

namespace App\Http\DTOs\Organization;

class OrganizationDto
{
    public ?string $name;
    public ?string $country;
    public ?string $city;
    public ?string $type;
    public ?string $address;
    public ?string $status;

    public function __construct(
        ?string $name = null,
        ?string $country = null,
        ?string $city = null,
        ?string $type = null,
        ?string $address = null,
        ?string $status = null,
    ) {
        $this->name    = $name;
        $this->country = $country;
        $this->city    = $city;
        $this->type    = $type;
        $this->address = $address;
        $this->status  = $status;
    }
}
