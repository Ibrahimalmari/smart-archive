<?php

namespace App\Http\Services\Department;

use App\Http\DTOs\Department\DepartmentDto;
use App\Http\Repositories\DepartmentRepositoryInterface;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class DepartmentService implements DepartmentServiceInterface
{
    private DepartmentRepositoryInterface $departments;

    public function __construct(DepartmentRepositoryInterface $departments)
    {
        $this->departments = $departments;
    }

    public function listByOrganization(int $organizationId, int $perPage = 15)
    {
        return $this->departments->paginateByOrganization($organizationId, $perPage);
    }

    public function find(int $id)
    {
        $dep = $this->departments->findById($id);

        if (! $dep) {
            throw ValidationException::withMessages([
                'department' => 'Department not found.',
            ]);
        }

        return $dep;
    }

    public function create(DepartmentDto $dto)
    {
        return $this->departments->create($dto);
    }

    public function update(int $id, DepartmentDto $dto)
    {
        return $this->departments->update($id, $dto);
    }

    public function delete(int $id)
    {
        // ممكن تضيف شرط: لا تحذف إذا عنده موظفين أو وثائق
        $this->departments->delete($id);
    }
}
