<?php

namespace Tests\Unit;

use App\Http\Services\Document\DocumentAccessService;
use App\Models\Document;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class DocumentAccessServiceTest extends TestCase
{
    public function test_employee_can_access_only_department_documents(): void
    {
        $service = new DocumentAccessService();
        $employee = new User([
            'id' => 10,
            'role' => 'Employee',
            'organization_id' => 1,
            'department_id' => 5,
        ]);

        $sameDepartmentDocument = new Document([
            'organization_id' => 1,
            'department_id' => 5,
            'uploaded_by' => 99,
        ]);

        $otherDepartmentDocument = new Document([
            'organization_id' => 1,
            'department_id' => 6,
            'uploaded_by' => 99,
        ]);

        $this->assertTrue($service->canAccess($employee, $sameDepartmentDocument));
        $this->assertFalse($service->canAccess($employee, $otherDepartmentDocument));
    }

    public function test_employee_can_submit_only_own_document_for_review(): void
    {
        $service = new DocumentAccessService();
        $employee = new User([
            'role' => 'Employee',
            'organization_id' => 1,
            'department_id' => 5,
        ]);
        $employee->id = 10;

        $ownDocument = new Document([
            'organization_id' => 1,
            'department_id' => 5,
            'uploaded_by' => 10,
        ]);

        $coworkerDocument = new Document([
            'organization_id' => 1,
            'department_id' => 5,
            'uploaded_by' => 11,
        ]);

        $this->assertTrue($service->canSubmitForReview($employee, $ownDocument));
        $this->assertFalse($service->canSubmitForReview($employee, $coworkerDocument));
    }

    public function test_manager_can_update_only_documents_inside_department(): void
    {
        $service = new DocumentAccessService();
        $manager = new User([
            'id' => 20,
            'role' => 'Manager',
            'organization_id' => 1,
            'department_id' => 5,
        ]);

        $sameDepartmentDocument = new Document([
            'organization_id' => 1,
            'department_id' => 5,
            'uploaded_by' => 10,
        ]);

        $otherDepartmentDocument = new Document([
            'organization_id' => 1,
            'department_id' => 6,
            'uploaded_by' => 10,
        ]);

        $this->assertTrue($service->canUpdate($manager, $sameDepartmentDocument));
        $this->assertFalse($service->canUpdate($manager, $otherDepartmentDocument));
    }

    public function test_admin_can_create_only_inside_organization(): void
    {
        $service = new DocumentAccessService();
        $admin = new User([
            'id' => 30,
            'role' => 'Admin',
            'organization_id' => 7,
            'department_id' => 2,
        ]);

        $this->assertTrue($service->canCreate($admin, 7, 2));
        $this->assertFalse($service->canCreate($admin, 8, 2));
    }
}
