<?php

namespace App\Http\Services\Auth;

use App\Http\DTOs\Auth\AddUserDto;
use App\Http\DTOs\Auth\LoginDto;
use App\Http\DTOs\Auth\UpdateUserDto;
use App\Http\DTOs\Auth\ToggleStatusDto;
interface AuthServiceInterface
{
    public function AddUser(AddUserDto $dto);
    public function login(LoginDto $dto);

    public function logoutAll(int $userId);


     // مستخدم يعدّل نفسه
    public function updateOwnProfile(int $userId, UpdateUserDto $dto);

    // Admin/Manager يعدّل أي مستخدم
    public function updateUserAsAdmin(int $userId, UpdateUserDto $dto);


    public function deleteUser(int $id);
    public function toggleUserStatus(int $id, ToggleStatusDto $dto);

}
