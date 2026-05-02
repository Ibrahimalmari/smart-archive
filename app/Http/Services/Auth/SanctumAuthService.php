<?php

namespace App\Http\Services\Auth;

use App\Http\DTOs\Auth\AddUserDto;
use App\Http\DTOs\Auth\LoginDto;
use App\Http\DTOs\Auth\ToggleStatusDto;
use App\Http\DTOs\Auth\UpdateUserDto;
use App\Http\Repositories\UserRepositoryInterface;
use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SanctumAuthService implements AuthServiceInterface
{
    private UserRepositoryInterface $users;

    public function __construct(UserRepositoryInterface $users)
    {
        $this->users = $users;
    }

    public function addUser(AddUserDto $dto, User $actor)
    {
        $assignableRoles = match ($actor->role) {
            'SuperAdmin' => ['SuperAdmin', 'Admin', 'Manager', 'Employee', 'Auditor'],
            'Admin' => ['Manager', 'Employee', 'Auditor'],
            default => [],
        };

        if (!in_array($dto->role, $assignableRoles, true)) {
            throw new AuthorizationException('You are not allowed to create this role.');
        }

        $organizationId = $dto->organization_id;
        $departmentId = $dto->department_id;

        if ($actor->role === 'Admin') {
            $organizationId = $actor->organization_id;
        }

        if ($departmentId !== null) {
            $department = Department::findOrFail($departmentId);

            if ($organizationId !== null && $department->organization_id !== $organizationId) {
                throw new AuthorizationException('Department does not belong to the selected organization.');
            }

            if ($actor->role === 'Admin' && $department->organization_id !== $actor->organization_id) {
                throw new AuthorizationException('You cannot assign departments outside your organization.');
            }
        }

        $data = [
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'role' => $dto->role,
            'organization_id' => $organizationId,
            'department_id' => $departmentId,
        ];

        $user = $this->users->create($data);
        $user->sendEmailVerificationNotification();

        return [
            'message' => 'User created. Verification email sent.',
            'user' => $user,
        ];
    }

    public function login(LoginDto $dto): array|null
    {
        if (!Auth::attempt([
            'email' => $dto->email,
            'password' => $dto->password,
        ])) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasVerifiedEmail()) {
            return [
                'status' => 'unverified',
                'message' => 'Email not verified. Please verify your email before logging in.',
            ];
        }

        if ($user->status === 'inactive') {
            return [
                'status' => 'inactive',
                'message' => 'Account is suspended',
            ];
        }

        $user->tokens()->delete();

        $abilities = match ($user->role) {
            'SuperAdmin', 'Admin' => ['*'],
            'Manager' => ['manage-users', 'view-users'],
            'Employee', 'Auditor' => ['view-users'],
            default => [],
        };

        $token = $user->createToken('auth_token', $abilities);
        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 120));

        $token->accessToken->expires_at = $expiresAt;
        $token->accessToken->save();

        return [
            'status' => 'ok',
            'user' => $user,
            'plain_token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function logoutAll(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->tokens()->delete();
    }

    public function updateOwnProfile(int $userId, UpdateUserDto $dto)
    {
        $data = [];

        $user = User::findOrFail($userId);

        if ($dto->name !== null) {
            $data['name'] = $dto->name;
        }

        if ($dto->email !== null && $dto->email !== $user->email) {
            $data['email'] = $dto->email;
            $data['email_verified_at'] = null;

            $user->forceFill(['email' => $dto->email, 'email_verified_at' => null]);
            $user->save();
            $user->sendEmailVerificationNotification();
        }

        if ($dto->password !== null) {
            $data['password'] = Hash::make($dto->password);
        }

        unset($data['role']);

        return $this->users->update($userId, $data);
    }

    public function updateUserAsAdmin(int $userId, UpdateUserDto $dto, User $actor)
    {
        $data = [];
        $user = User::findOrFail($userId);

        $this->assertCanManageUser($actor, $user);

        $organizationId = $this->resolveManagedOrganizationId($actor, $user, $dto->organization_id);
        $departmentId = $this->resolveManagedDepartmentId(
            $actor,
            $user,
            $dto->department_id,
            $organizationId ?? $user->organization_id,
        );

        if ($dto->name !== null) {
            $data['name'] = $dto->name;
        }

        if ($dto->email !== null && $dto->email !== $user->email) {
            $data['email'] = $dto->email;
            $data['email_verified_at'] = null;

            $user->forceFill(['email' => $dto->email, 'email_verified_at' => null]);
            $user->save();
            $user->sendEmailVerificationNotification();
        }

        if ($dto->password !== null) {
            $data['password'] = Hash::make($dto->password);
        }

        if ($dto->role !== null) {
            $this->assertAssignableRole($actor, $dto->role);
            $data['role'] = $dto->role;
        }

        if ($organizationId !== null) {
            $data['organization_id'] = $organizationId;
        }

        if ($departmentId !== null) {
            $data['department_id'] = $departmentId;
        } elseif ($organizationId !== null && (int) $organizationId !== (int) $user->organization_id) {
            $data['department_id'] = null;
        }

        return $this->users->update($userId, $data);
    }

    public function deleteUser(int $id, User $actor)
    {
        $user = User::findOrFail($id);

        if ((int) $user->id === (int) $actor->id) {
            throw new AuthorizationException('You cannot delete your own account.');
        }

        $this->assertCanManageUser($actor, $user);
        $user->tokens()->delete();

        return $user->delete();
    }

    public function toggleUserStatus(int $id, ToggleStatusDto $dto, User $actor)
    {
        $user = User::findOrFail($id);

        if ((int) $user->id === (int) $actor->id) {
            throw new AuthorizationException('You cannot change your own status.');
        }

        $this->assertCanManageUser($actor, $user);

        return $this->users->updateStatus($id, $dto->status);
    }

    /**
     * @return array<int, string>
     */
    private function manageableRoles(User $actor): array
    {
        return match ($actor->role) {
            'SuperAdmin' => ['SuperAdmin', 'Admin', 'Manager', 'Employee', 'Auditor'],
            'Admin' => ['Manager', 'Employee', 'Auditor'],
            'Manager' => ['Employee', 'Auditor'],
            default => [],
        };
    }

    private function assertCanManageUser(User $actor, User $target): void
    {
        $manageableRoles = $this->manageableRoles($actor);

        if ($manageableRoles === []) {
            throw new AuthorizationException('You are not allowed to manage users.');
        }

        if (!in_array($target->role, $manageableRoles, true)) {
            throw new AuthorizationException('You are not allowed to manage this user.');
        }

        if ($actor->role === 'Admin' && (int) $target->organization_id !== (int) $actor->organization_id) {
            throw new AuthorizationException('You cannot manage users outside your organization.');
        }

        if ($actor->role === 'Manager') {
            if ((int) $target->organization_id !== (int) $actor->organization_id) {
                throw new AuthorizationException('You cannot manage users outside your organization.');
            }

            if ((int) $target->department_id !== (int) $actor->department_id) {
                throw new AuthorizationException('You cannot manage users outside your department.');
            }
        }
    }

    private function assertAssignableRole(User $actor, string $role): void
    {
        if (!in_array($role, $this->manageableRoles($actor), true)) {
            throw new AuthorizationException('You are not allowed to assign this role.');
        }
    }

    private function resolveManagedOrganizationId(User $actor, User $target, ?int $organizationId): ?int
    {
        if ($organizationId === null) {
            return null;
        }

        if ($actor->role === 'SuperAdmin') {
            return $organizationId;
        }

        if ((int) $organizationId !== (int) $actor->organization_id) {
            throw new AuthorizationException('You cannot assign users outside your organization.');
        }

        if ($actor->role === 'Manager' && (int) $organizationId !== (int) $target->organization_id) {
            throw new AuthorizationException('You cannot change organization assignment.');
        }

        return $organizationId;
    }

    private function resolveManagedDepartmentId(
        User $actor,
        User $target,
        ?int $departmentId,
        ?int $organizationId
    ): ?int {
        if ($departmentId === null) {
            return null;
        }

        $department = Department::findOrFail($departmentId);
        $effectiveOrganizationId = $organizationId ?? $target->organization_id;

        if ((int) $department->organization_id !== (int) $effectiveOrganizationId) {
            throw new AuthorizationException('Department does not belong to the selected organization.');
        }

        if ($actor->role === 'Admin' && (int) $department->organization_id !== (int) $actor->organization_id) {
            throw new AuthorizationException('You cannot assign departments outside your organization.');
        }

        if ($actor->role === 'Manager' && (int) $department->id !== (int) $actor->department_id) {
            throw new AuthorizationException('You cannot assign users outside your department.');
        }

        return $departmentId;
    }
}
