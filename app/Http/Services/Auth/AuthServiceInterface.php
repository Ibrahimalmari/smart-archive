<?php

namespace App\Http\Services\Auth;

use App\Http\DTOs\Auth\AddUserDto;
use App\Http\DTOs\Auth\LoginDto;
use App\Http\DTOs\Auth\ToggleStatusDto;
use App\Http\DTOs\Auth\UpdateUserDto;
use App\Models\User;

interface AuthServiceInterface
{
    public function addUser(AddUserDto $dto, User $actor);

    public function login(LoginDto $dto);

    public function logoutAll(int $userId);

    public function updateOwnProfile(int $userId, UpdateUserDto $dto);

    public function updateUserAsAdmin(int $userId, UpdateUserDto $dto, User $actor);

    public function deleteUser(int $id, User $actor);

    public function toggleUserStatus(int $id, ToggleStatusDto $dto, User $actor);
}
