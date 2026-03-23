<?php

namespace App\Http\Repositories;

use App\Http\DTOs\Department\DepartmentDto;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DepartmentRepositoryInterface
{
    public function paginateByOrganization(int $organizationId, int $perPage = 15);

    public function findById(int $id);

    public function create(DepartmentDto $dto);

    public function update(int $id, DepartmentDto $dto);

    public function delete(int $id);
}
