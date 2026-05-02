<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_only_department_documents(): void
    {
        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $depA = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT',
            'code' => 'IT',
            'status' => 'active',
        ]);

        $depB = Department::create([
            'organization_id' => $organization->id,
            'name' => 'HR',
            'code' => 'HR',
            'status' => 'active',
        ]);

        $userA = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $depA->id,
            'email_verified_at' => now(),
        ]);

        $userB = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $depB->id,
            'email_verified_at' => now(),
        ]);

        $docA = Document::create([
            'title' => 'Doc A',
            'description' => null,
            'original_name' => 'a.pdf',
            'path' => 'documents/a.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1,
            'organization_id' => $organization->id,
            'department_id' => $depA->id,
            'uploaded_by' => $userA->id,
        ]);
        $docA->workflow()->create(['status' => Document::STATUS_PENDING]);

        $docB = Document::create([
            'title' => 'Doc B',
            'description' => null,
            'original_name' => 'b.pdf',
            'path' => 'documents/b.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1,
            'organization_id' => $organization->id,
            'department_id' => $depB->id,
            'uploaded_by' => $userB->id,
        ]);
        $docB->workflow()->create(['status' => Document::STATUS_PENDING]);

        Sanctum::actingAs($userA);

        $index = $this->getJson('/api/documents');
        $index->assertOk();
        $this->assertCount(1, $index->json());
        $this->assertSame($docA->id, $index->json()[0]['id']);

        $showForbidden = $this->getJson('/api/documents/' . $docB->id);
        $showForbidden->assertStatus(403);
    }
}
