<?php

namespace App\Http\Repositories;

use App\Http\DTOs\Organization\OrganizationDto;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Organization::query()
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Organization
    {
        return Organization::find($id);
    }

    public function create(OrganizationDto $dto): Organization
    {
        return Organization::create([
            'name'    => $dto->name,
            'country' => $dto->country,
            'city'    => $dto->city,
            'type'    => $dto->type,
            'address' => $dto->address,
            'status'  => $dto->status,
        ]);
    }

    public function update(int $id, OrganizationDto $dto): Organization
    {
        $org = Organization::findOrFail($id);

        // Only update fields that are provided (not null) to allow partial updates
        $updates = array_filter([
            'name'    => $dto->name,
            'country' => $dto->country,
            'city'    => $dto->city,
            'type'    => $dto->type,
            'address' => $dto->address,
            'status'  => $dto->status,
        ], fn($v) => $v !== null);

        if (! empty($updates)) {
            $org->update($updates);
        }

        return $org->fresh();
    }

    public function delete(int $id): bool
    {
        return Organization::destroy($id) > 0;
    }
}
