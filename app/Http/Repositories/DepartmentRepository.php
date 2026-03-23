<?php

namespace App\Http\Repositories;

use App\Http\DTOs\Department\DepartmentDto;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DepartmentRepository implements DepartmentRepositoryInterface
{
    public function paginateByOrganization(int $organizationId, int $perPage = 15): LengthAwarePaginator
    {
        return Department::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Department
    {
        return Department::find($id);
    }

    public function create(DepartmentDto $dto): Department
    {
        return Department::create([
            'organization_id' => $dto->organizationId,
            'name'            => $dto->name,
            'code'            => $dto->code,
            'status'          => $dto->status,
        ]);
    }

    public function update(int $id, DepartmentDto $dto): Department
    {
        $dep = Department::findOrFail($id);

        // Update only provided (non-null) fields to support partial updates
        $updates = array_filter([
            'organization_id' => $dto->organizationId,
            'name'            => $dto->name,
            'code'            => $dto->code,
            'status'          => $dto->status,
        ], fn($v) => $v !== null);

        if (! empty($updates)) {
            $dep->update($updates);
        }

        return $dep->fresh();
    }

    public function delete(int $id): bool
    {
        return Department::destroy($id) > 0;
    }
}
