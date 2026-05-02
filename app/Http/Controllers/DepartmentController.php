<?php

namespace App\Http\Controllers;

use App\Http\DTOs\Department\DepartmentDto;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Services\Department\DepartmentServiceInterface;
use App\Models\Department;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentController extends Controller
{
    private DepartmentServiceInterface $departments;

    public function __construct(DepartmentServiceInterface $departments)
    {
        $this->departments = $departments;
    }

    public function index(Request $request, int $orgId)
    {
        $user = Auth::user();
        if ($response = $this->ensureDepartmentAdminRole($user)) {
            return $response;
        }

        if ($user->role === 'Admin' && $orgId !== $user->organization_id) {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول لهذه المنظمة'], 403);
        }

        $items = $this->departments->listByOrganization(
            $orgId,
            (int) $request->get('per_page', 15)
        );

        return response()->json($items);
    }

    public function show(int $id)
    {
        $user = Auth::user();
        if ($response = $this->ensureDepartmentAdminRole($user)) {
            return $response;
        }

        $department = Department::find($id);
        if (!$department) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        if ($user->role === 'Admin' && $department->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول لهذا القسم'], 403);
        }

        return response()->json($department);
    }

    public function store(StoreDepartmentRequest $request, int $orgId)
    {
        $user = Auth::user();
        if ($response = $this->ensureDepartmentAdminRole($user)) {
            return $response;
        }

        $organization = Organization::find($orgId);
        if (!$organization) {
            return response()->json(['message' => 'المنظمة غير موجودة'], 404);
        }

        if ($user->role === 'Admin' && $orgId !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك إضافة أقسام خارج منظمتك'], 403);
        }

        $data = $request->validated();

        $department = $this->departments->create(new DepartmentDto(
            organizationId: $orgId,
            name: $data['name'],
            code: $data['code'],
            status: $data['status'] ?? 'active',
        ));

        return response()->json($department, 201);
    }

    public function update(UpdateDepartmentRequest $request, int $id)
    {
        $user = Auth::user();
        if ($response = $this->ensureDepartmentAdminRole($user)) {
            return $response;
        }

        $department = Department::find($id);
        if (!$department) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        if ($user->role === 'Admin' && $department->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك تعديل أقسام خارج منظمتك'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50',
            'status' => 'sometimes|nullable|in:active,inactive',
        ]);

        $allData = array_merge($_POST ?? [], $request->all());

        $updateData = [];
        if (!empty($allData['name'] ?? null)) {
            $updateData['name'] = trim($allData['name']);
        }
        if (!empty($allData['code'] ?? null)) {
            $updateData['code'] = trim($allData['code']);
        }
        if (!empty($allData['status'] ?? null)) {
            $updateData['status'] = trim($allData['status']);
        }

        if (!empty($updateData)) {
            $department->update($updateData);
        }

        return response()->json($department);
    }

    public function destroy(int $id)
    {
        $user = Auth::user();
        if ($response = $this->ensureDepartmentAdminRole($user)) {
            return $response;
        }

        $department = Department::find($id);
        if (!$department) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        if ($user->role === 'Admin' && $department->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك حذف أقسام خارج منظمتك'], 403);
        }

        $this->departments->delete($id);

        return response()->json(['message' => 'تم حذف القسم بنجاح']);
    }

    private function ensureDepartmentAdminRole($user): ?JsonResponse
    {
        if (!$user || !in_array($user->role, ['SuperAdmin', 'Admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }
}
