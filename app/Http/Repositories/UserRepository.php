<?php

namespace App\Http\Repositories;

use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data)
    {
        return User::create($data);
    }

     public function update(int $id, array $data): User
{
    $user = User::findOrFail($id);
    $user->update($data);
    return $user;
}


public function delete(int $id)
{
    return User::destroy($id);
}

public function updateStatus(int $id, string $status)
{
    $user = User::findOrFail($id);
    $user->status = $status;
    $user->save();

    return $user;
}

}
