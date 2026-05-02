<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_is_classified_on_upload(): void
    {
        Storage::fake('public');

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

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf');

        $response = $this->post('/api/documents/add', [
            'title' => 'فاتورة صيانة',
            'description' => 'invoice payment',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'file' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('document_type', 'invoice');

        $this->assertDatabaseHas('documents', [
            'title' => 'فاتورة صيانة',
            'document_type' => 'invoice',
            'classification_source' => 'metadata',
        ]);
    }

    public function test_forbidden_upload_does_not_store_file(): void
    {
        Storage::fake('public');

        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $departmentA = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT',
            'code' => 'IT',
            'status' => 'active',
        ]);

        $departmentB = Department::create([
            'organization_id' => $organization->id,
            'name' => 'HR',
            'code' => 'HR',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $departmentA->id,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('forbidden.pdf', 120, 'application/pdf');
        $filesBeforeRequest = Storage::disk('public')->allFiles('documents');

        $response = $this->post('/api/documents/add', [
            'title' => 'Forbidden Upload',
            'organization_id' => $organization->id,
            'department_id' => $departmentB->id,
            'file' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(403);
        $this->assertSame($filesBeforeRequest, Storage::disk('public')->allFiles('documents'));
        $this->assertDatabaseCount('documents', 0);
    }
}
