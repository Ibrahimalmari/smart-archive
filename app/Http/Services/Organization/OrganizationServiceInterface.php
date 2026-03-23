<?php

namespace App\Http\Services\Organization;

use App\Http\DTOs\Organization\OrganizationDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Organization;

interface OrganizationServiceInterface
{
    public function list(int $perPage = 15);

    public function find(int $id);

    public function create(OrganizationDto $dto);

    public function update(int $id, OrganizationDto $dto);

    public function delete(int $id);
}
