<?php

namespace App\Http\Repositories;

use App\Http\DTOs\Organization\OrganizationDto;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrganizationRepositoryInterface
{
    public function paginate(int $perPage = 15);

    public function findById(int $id);

    public function create(OrganizationDto $dto);

    public function update(int $id, OrganizationDto $dto);

    public function delete(int $id);
}
