<?php

namespace App\Http\Services\Department;

use App\Http\DTOs\Department\DepartmentDto;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DepartmentServiceInterface
{
    public function listByOrganization(int $organizationId, int $perPage = 15);

    public function find(int $id);

    public function create(DepartmentDto $dto);

    public function update(int $id, DepartmentDto $dto);

    public function delete(int $id);
}
