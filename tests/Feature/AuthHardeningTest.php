<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_create_users(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'User',
            'email' => 'new@example.com',
            'password' => 'secret123',
            'role' => 'Employee',
            'organization_id' => 1,
            'department_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_cannot_create_superadmin(): void
    {
        Notification::fake();

        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'Admin',
            'status' => 'active',
            'organization_id' => $organization->id,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'name' => 'New SA',
            'email' => 'sa@example.com',
            'password' => 'secret123',
            'role' => 'SuperAdmin',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'sa@example.com']);
    }

    public function test_admin_creates_employee_in_own_organization(): void
    {
        Notification::fake();

        $orgA = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $orgB = Organization::create([
            'name' => 'Org B',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr 2',
            'status' => 'active',
        ]);

        $depA = Department::create([
            'organization_id' => $orgA->id,
            'name' => 'HR',
            'code' => 'HR',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'Admin',
            'status' => 'active',
            'organization_id' => $orgA->id,
            'department_id' => $depA->id,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'name' => 'Employee X',
            'email' => 'empx@example.com',
            'password' => 'secret123',
            'role' => 'Employee',
            'organization_id' => $orgB->id,
            'department_id' => $depA->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.organization_id', $orgA->id);
        $response->assertJsonPath('user.role', 'Employee');
    }

    public function test_login_response_has_plain_token_and_expires_at(): void
    {
        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $department = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT',
            'code' => 'IT',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user',
                'plain_token',
                'expires_at',
            ])
            ->assertJsonMissingPath('token');
    }

    public function test_unverified_user_cannot_login(): void
    {
        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $department = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT',
            'code' => 'IT',
            'status' => 'active',
        ]);

        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Email not verified. Please verify your email before logging in.');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $department = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT',
            'code' => 'IT',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'Employee',
            'status' => 'inactive',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Account is suspended');
    }

    public function test_logout_all_revokes_all_tokens(): void
    {
        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $department = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT',
            'code' => 'IT',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $user->createToken('first-token');
        $token = $user->createToken('second-token');

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/logout-all');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNotNull($token->plainTextToken);
    }

    public function test_email_resend_uses_authenticated_user_only(): void
    {
        Notification::fake();

        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $department = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT',
            'code' => 'IT',
            'status' => 'active',
        ]);

        $actor = User::factory()->unverified()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
        ]);

        $other = User::factory()->unverified()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
        ]);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/email/resend', [
            'email' => $other->email,
        ]);

        $response->assertOk();
        Notification::assertSentTo($actor, EmailVerificationNotification::class);
        Notification::assertNotSentTo($other, EmailVerificationNotification::class);
    }
}
