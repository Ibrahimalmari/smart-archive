<?php

namespace App\Http\Controllers;

use App\Http\DTOs\Department\DepartmentDto;
use App\Http\Services\Department\DepartmentServiceInterface;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Department;
use App\Models\Organization;

class DepartmentController extends Controller
{
    private DepartmentServiceInterface $departments;

    public function __construct(DepartmentServiceInterface $departments)
    {
        $this->departments = $departments;
    }

    /**
     * عرض أقسام المؤسسة
     * - SuperAdmin: يرى كل المؤسسات
     * - Admin: يرى فقط أقسام منظمته
     */
    public function index(Request $request, int $orgId)
    {
        $user = Auth::user();

        // Admin يستطيع فقط رؤية أقسام منظمته
        if ($user->role === 'Admin' && $orgId !== $user->organization_id) {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول لهذه المنظمة'], 403);
        }

        $items = $this->departments->listByOrganization(
            $orgId,
            (int) $request->get('per_page', 15)
        );

        return response()->json($items);
    }

    /**
     * عرض قسم واحد
     * - SuperAdmin: يرى أي قسم
     * - Admin: يرى فقط أقسام منظمته
     */
    public function show(int $id)
    {
        $user = Auth::user();
        $dep = Department::find($id);

        if (!$dep) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        // Admin يستطيع فقط رؤية أقسام منظمته
        if ($user->role === 'Admin' && $dep->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول لهذا القسم'], 403);
        }

        return response()->json($dep);
    }

    /**
     * إضافة قسم جديد
     * - SuperAdmin: يضيف في أي منظمة
     * - Admin: يضيف فقط في منظمته
     */
    public function store(StoreDepartmentRequest $request, int $orgId)
    {
        $user = Auth::user();

        // التحقق من وجود المنظمة
        $org = Organization::find($orgId);
        if (!$org) {
            return response()->json(['message' => 'المنظمة غير موجودة'], 404);
        }

        // Admin يستطيع فقط إضافة في منظمته
        if ($user->role === 'Admin' && $orgId !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك إضافة أقسام خارج منظمتك'], 403);
        }

        $data = $request->validated();

        $dto = new DepartmentDto(
            organizationId: $orgId,
            name: $data['name'],
            code: $data['code'],
            status: $data['status'] ?? 'active',
        );

        $dep = $this->departments->create($dto);

        return response()->json($dep, 201);
    }

    /**
     * تعديل قسم
     * - SuperAdmin: يعدل أي قسم
     * - Admin: يعدل فقط أقسام منظمته
     */
    public function update(UpdateDepartmentRequest $request, int $id)
    {
        $user = Auth::user();
        $dep = Department::find($id);

        if (!$dep) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        // Admin يستطيع فقط تعديل أقسام منظمته
        if ($user->role === 'Admin' && $dep->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك تعديل أقسام خارج منظمتك'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50',
            'status' => 'sometimes|nullable|in:active,inactive',
        ]);

        // قراءة البيانات من $_POST و $request->all() معاً
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
            $dep->update($updateData);
        }

        return response()->json($dep);
    }

    /**
     * حذف قسم
     * - SuperAdmin: يحذف أي قسم
     * - Admin: يحذف فقط أقسام منظمته
     */
    public function destroy(int $id)
    {
        $user = Auth::user();
        $dep = Department::find($id);

        if (!$dep) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        // Admin يستطيع فقط حذف أقسام منظمته
        if ($user->role === 'Admin' && $dep->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك حذف أقسام خارج منظمتك'], 403);
        }

        $this->departments->delete($id);

        return response()->json(['message' => 'تم حذف القسم بنجاح']);
    }
}
