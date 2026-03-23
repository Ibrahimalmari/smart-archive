<?php

namespace App\Http\Services\Organization;

use App\Http\DTOs\Organization\OrganizationDto;
use App\Http\Repositories\OrganizationRepositoryInterface;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class OrganizationService implements OrganizationServiceInterface
{
    private OrganizationRepositoryInterface $organizations;

    public function __construct(OrganizationRepositoryInterface $organizations)
    {
        $this->organizations = $organizations;
    }

    public function list(int $perPage = 15)
    {
        return $this->organizations->paginate($perPage);
    }

    public function find(int $id)
    {
        $org = $this->organizations->findById($id);

        if (! $org) {
            throw ValidationException::withMessages([
                'organization' => 'Organization not found.',
            ]);
        }

        return $org;
    }

    public function create(OrganizationDto $dto)
    {
        return $this->organizations->create($dto);
    }

    public function update(int $id, OrganizationDto $dto)
    {
        return $this->organizations->update($id, $dto);
    }

    public function delete(int $id): void
    {
        // ممكن تضيف هنا شرط: لا تحذف إذا فيها أقسام أو موظفين
        $this->organizations->delete($id);
    }
}
