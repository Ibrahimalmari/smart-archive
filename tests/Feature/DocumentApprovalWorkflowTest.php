<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_submit_approve_archive_reopen_and_history(): void
    {
        [$organization, $department] = $this->seedOrganizationAndDepartment();

        $employee = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role' => 'Manager',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $document = $this->seedDocument($organization->id, $department->id, $employee->id, Document::STATUS_PENDING);

        Sanctum::actingAs($employee);
        $submit = $this->postJson('/api/documents/' . $document->id . '/submit', [
            'notes' => 'Ready for review',
        ]);

        $submit->assertOk()
            ->assertJsonPath('document.status', Document::STATUS_UNDER_REVIEW);
        $this->assertDatabaseHas('document_approval_logs', [
            'document_id' => $document->id,
            'action' => 'submit',
            'from_status' => Document::STATUS_PENDING,
            'to_status' => Document::STATUS_UNDER_REVIEW,
            'acted_by' => $employee->id,
        ]);

        Sanctum::actingAs($manager);
        $approve = $this->postJson('/api/documents/' . $document->id . '/approve', [
            'approval_notes' => 'Approved by manager',
        ]);

        $approve->assertOk()
            ->assertJsonPath('document.status', Document::STATUS_APPROVED);
        $this->assertDatabaseHas('document_approval_logs', [
            'document_id' => $document->id,
            'action' => 'approve',
            'from_status' => Document::STATUS_UNDER_REVIEW,
            'to_status' => Document::STATUS_APPROVED,
            'acted_by' => $manager->id,
        ]);

        $archive = $this->postJson('/api/documents/' . $document->id . '/archive', [
            'archive_reason' => 'Retention complete',
        ]);

        $archive->assertOk()
            ->assertJsonPath('document.status', Document::STATUS_ARCHIVED);

        $reopen = $this->postJson('/api/documents/' . $document->id . '/reopen', [
            'notes' => 'Need one more review cycle',
        ]);

        $reopen->assertOk()
            ->assertJsonPath('document.status', Document::STATUS_UNDER_REVIEW);

        Sanctum::actingAs($employee);
        $history = $this->getJson('/api/documents/' . $document->id . '/history');

        $history->assertOk()
            ->assertJsonCount(4, 'history')
            ->assertJsonPath('history.0.action', 'submit')
            ->assertJsonPath('history.1.action', 'approve')
            ->assertJsonPath('history.2.action', 'archive')
            ->assertJsonPath('history.3.action', 'reopen');
    }

    public function test_employee_cannot_approve_document(): void
    {
        [$organization, $department] = $this->seedOrganizationAndDepartment();

        $employee = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $document = $this->seedDocument($organization->id, $department->id, $employee->id, Document::STATUS_UNDER_REVIEW);

        Sanctum::actingAs($employee);
        $response = $this->postJson('/api/documents/' . $document->id . '/approve', [
            'approval_notes' => 'try',
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_cannot_approve_document_outside_department(): void
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

        $manager = User::factory()->create([
            'role' => 'Manager',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $depA->id,
            'email_verified_at' => now(),
        ]);

        $employeeB = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $depB->id,
            'email_verified_at' => now(),
        ]);

        $document = $this->seedDocument($organization->id, $depB->id, $employeeB->id, Document::STATUS_UNDER_REVIEW);

        Sanctum::actingAs($manager);
        $response = $this->postJson('/api/documents/' . $document->id . '/approve', [
            'approval_notes' => 'approve',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_and_delete_are_blocked_after_approval(): void
    {
        [$organization, $department] = $this->seedOrganizationAndDepartment();

        $manager = User::factory()->create([
            'role' => 'Manager',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $employee = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $document = $this->seedDocument($organization->id, $department->id, $employee->id, Document::STATUS_APPROVED);

        Sanctum::actingAs($manager);

        $update = $this->putJson('/api/documents/' . $document->id, [
            'title' => 'Updated title',
        ]);
        $update->assertStatus(422)
            ->assertJsonPath('current_status', Document::STATUS_APPROVED);

        $delete = $this->deleteJson('/api/documents/' . $document->id);
        $delete->assertStatus(422)
            ->assertJsonPath('current_status', Document::STATUS_APPROVED);
    }

    private function seedOrganizationAndDepartment(): array
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

        return [$organization, $department];
    }

    private function seedDocument(int $organizationId, int $departmentId, int $uploadedBy, string $status): Document
    {
        $document = Document::create([
            'title' => 'Workflow Document',
            'description' => null,
            'original_name' => 'workflow.pdf',
            'path' => 'documents/workflow.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'organization_id' => $organizationId,
            'department_id' => $departmentId,
            'uploaded_by' => $uploadedBy,
        ]);

        $document->workflow()->create([
            'status' => $status,
        ]);

        return $document;
    }
}
