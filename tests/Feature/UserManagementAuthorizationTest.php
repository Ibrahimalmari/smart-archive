<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_cannot_update_user_outside_department(): void
    {
        [$organization, $departmentA, $departmentB] = $this->seedOrganizationWithDepartments();

        $manager = $this->createUser('Manager', $organization, $departmentA, 'manager@example.com');
        $employee = $this->createUser('Employee', $organization, $departmentB, 'employee-b@example.com');

        Sanctum::actingAs($manager);

        $this->postJson('/api/users/' . $employee->id, [
            'name' => 'Changed Name',
        ])->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => $employee->name,
        ]);
    }

    public function test_admin_cannot_promote_user_to_admin(): void
    {
        [$organization, $department] = $this->seedOrganizationWithDepartments(1, false);

        $admin = $this->createUser('Admin', $organization, $department, 'admin@example.com');
        $employee = $this->createUser('Employee', $organization, $department, 'employee@example.com');

        Sanctum::actingAs($admin);

        $this->postJson('/api/users/' . $employee->id, [
            'role' => 'Admin',
        ])->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'role' => 'Employee',
        ]);
    }

    public function test_admin_cannot_move_user_outside_organization(): void
    {
        [$organizationA, $departmentA] = $this->seedOrganizationWithDepartments(1, false);
        [$organizationB, $departmentB] = $this->seedOrganizationWithDepartments(2, false);

        $admin = $this->createUser('Admin', $organizationA, $departmentA, 'admin@example.com');
        $employee = $this->createUser('Employee', $organizationA, $departmentA, 'employee@example.com');

        Sanctum::actingAs($admin);

        $this->postJson('/api/users/' . $employee->id, [
            'organization_id' => $organizationB->id,
            'department_id' => $departmentB->id,
        ])->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'organization_id' => $organizationA->id,
            'department_id' => $departmentA->id,
        ]);
    }

    public function test_manager_cannot_toggle_status_for_user_outside_department(): void
    {
        [$organization, $departmentA, $departmentB] = $this->seedOrganizationWithDepartments();

        $manager = $this->createUser('Manager', $organization, $departmentA, 'manager@example.com');
        $employee = $this->createUser('Employee', $organization, $departmentB, 'employee-b@example.com');

        Sanctum::actingAs($manager);

        $this->postJson('/api/users/' . $employee->id . '/status', [
            'status' => 'inactive',
        ])->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_cannot_delete_admin_user(): void
    {
        [$organization, $department] = $this->seedOrganizationWithDepartments(1, false);

        $admin = $this->createUser('Admin', $organization, $department, 'admin@example.com');
        $otherAdmin = $this->createUser('Admin', $organization, $department, 'other-admin@example.com');

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/users/delete/' . $otherAdmin->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'email' => 'other-admin@example.com',
        ]);
    }

    /**
     * @return array{0: Organization, 1: Department, 2?: Department}
     */
    private function seedOrganizationWithDepartments(int $suffix = 1, bool $withSecondDepartment = true): array
    {
        $organization = Organization::create([
            'name' => 'Org ' . $suffix,
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr ' . $suffix,
            'status' => 'active',
        ]);

        $departmentA = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT ' . $suffix,
            'code' => 'IT' . $suffix,
            'status' => 'active',
        ]);

        if (!$withSecondDepartment) {
            return [$organization, $departmentA];
        }

        $departmentB = Department::create([
            'organization_id' => $organization->id,
            'name' => 'HR ' . $suffix,
            'code' => 'HR' . $suffix,
            'status' => 'active',
        ]);

        return [$organization, $departmentA, $departmentB];
    }

    private function createUser(string $role, Organization $organization, Department $department, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'role' => $role,
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);
    }
}
