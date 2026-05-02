<?php

namespace Tests\Feature;

use App\Http\Services\Document\DocumentWorkflowService;
use App\Http\Services\Document\DocumentWorkflowTransitionException;
use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_resets_review_fields_and_logs_history(): void
    {
        [$organization, $department] = $this->seedOrganizationAndDepartment();

        $employee = $this->createUser('Employee', $organization, $department);
        $reviewer = $this->createUser('Manager', $organization, $department, 'reviewer@example.com');

        $document = Document::create([
            'title' => 'Workflow Document',
            'description' => null,
            'original_name' => 'workflow.pdf',
            'path' => 'documents/workflow.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'uploaded_by' => $employee->id,
        ]);
        $document->workflow()->create([
            'status' => Document::STATUS_REJECTED,
            'reviewed_at' => now()->subDay(),
            'reviewed_by' => $reviewer->id,
            'rejected_at' => now()->subDay(),
            'rejected_by' => $reviewer->id,
            'rejection_reason' => 'Old reason',
            'approval_notes' => 'Old notes',
            'archive_reason' => 'Old archive reason',
        ]);

        $service = new DocumentWorkflowService();
        $updated = $service->submit($document, $employee, 'Retry review');

        $this->assertSame(Document::STATUS_UNDER_REVIEW, $updated->status);
        $this->assertSame($employee->id, $updated->submitted_by);
        $this->assertNull($updated->reviewed_by);
        $this->assertNull($updated->rejected_by);
        $this->assertNull($updated->rejection_reason);
        $this->assertDatabaseHas('document_approval_logs', [
            'document_id' => $document->id,
            'action' => 'submit',
            'from_status' => Document::STATUS_REJECTED,
            'to_status' => Document::STATUS_UNDER_REVIEW,
            'acted_by' => $employee->id,
            'notes' => 'Retry review',
        ]);
    }

    public function test_approve_throws_for_invalid_transition(): void
    {
        [$organization, $department] = $this->seedOrganizationAndDepartment();
        $manager = $this->createUser('Manager', $organization, $department);

        $document = Document::create([
            'title' => 'Workflow Document',
            'description' => null,
            'original_name' => 'workflow.pdf',
            'path' => 'documents/workflow.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'uploaded_by' => $manager->id,
        ]);
        $document->workflow()->create([
            'status' => Document::STATUS_PENDING,
        ]);

        $service = new DocumentWorkflowService();

        try {
            $service->approve($document, $manager, 'Approve');
            $this->fail('Expected invalid transition exception was not thrown.');
        } catch (DocumentWorkflowTransitionException $exception) {
            $this->assertSame('approve', $exception->action);
            $this->assertSame(Document::STATUS_PENDING, $exception->currentStatus);
            $this->assertSame([Document::STATUS_UNDER_REVIEW], $exception->allowedStatuses);
        }
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

    private function createUser(string $role, Organization $organization, Department $department, string $email = 'employee@example.com'): User
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
