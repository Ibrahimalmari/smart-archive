<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_access_department_admin_routes(): void
    {
        $organization = $this->createOrganization('Org A');
        $department = $this->createDepartment($organization, 'IT', 'IT');

        $employee = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/organizations/' . $organization->id . '/departments')
            ->assertStatus(403);

        $this->postJson('/api/organizations/' . $organization->id . '/departments', [
            'name' => 'Finance',
            'code' => 'FIN',
            'status' => 'active',
        ])->assertStatus(403);
    }

    public function test_admin_cannot_manage_departments_outside_organization(): void
    {
        $organizationA = $this->createOrganization('Org A');
        $organizationB = $this->createOrganization('Org B');
        $adminDepartment = $this->createDepartment($organizationA, 'IT', 'IT');
        $foreignDepartment = $this->createDepartment($organizationB, 'HR', 'HR');

        $admin = User::factory()->create([
            'role' => 'Admin',
            'status' => 'active',
            'organization_id' => $organizationA->id,
            'department_id' => $adminDepartment->id,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/organizations/' . $organizationB->id . '/departments')
            ->assertStatus(403);

        $this->putJson('/api/departments/' . $foreignDepartment->id, [
            'name' => 'HR Updated',
        ])->assertStatus(403);
    }

    private function createOrganization(string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);
    }

    private function createDepartment(Organization $organization, string $name, string $code): Department
    {
        return Department::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'code' => $code,
            'status' => 'active',
        ]);
    }
}
