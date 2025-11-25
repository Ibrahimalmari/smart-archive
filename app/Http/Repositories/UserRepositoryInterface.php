<?php

namespace App\Http\Repositories;

interface UserRepositoryInterface
{
    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id);
    public function updateStatus(int $id, string $status);

}
