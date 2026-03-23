<?php

namespace App\Http\Controllers;

use App\Http\Services\Document\DocumentServiceInterface;
use App\Http\DTOs\Document\DocumentDto;
use App\Http\Requests\StoreDocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;

class DocumentController extends Controller
{
    private DocumentServiceInterface $documents;

    public function __construct(DocumentServiceInterface $documents)
    {
        $this->documents = $documents;
    }

    /**
     * إضافة وثيقة جديدة
     * - منع رفع نفس الملف مرتين (نفس المحتوى)
     * - Employees: في قسمهم فقط
     * - Managers: في قسمهم فقط
     * - Admins: في منظمتهم (أي قسم)
     * - SuperAdmin: في أي مكان
     */
    public function add(StoreDocumentRequest $request)
    {
        $user = Auth::user();
        
        $validated = $request->validated();

        $file = $request->file('file');
        
        // حساب hash الملف
        $fileHash = hash_file('sha256', $file->getRealPath());
        
        // التحقق من عدم وجود نفس الملف من نفس المستخدم
        $existingDocument = Document::where('uploaded_by', $user->id)
            ->whereRaw("SHA2(CONCAT(path, title), 256) = ?", [$fileHash])
            ->first();
            
        // بطريقة أبسط: نتحقق من وجود ملف بنفس الحجم والـ mime type من نفس المستخدم
        $existingDocument = Document::where('uploaded_by', $user->id)
            ->where('size', $file->getSize())
            ->where('mime_type', $file->getClientMimeType())
            ->where('original_name', $file->getClientOriginalName())
            ->first();
            
        if ($existingDocument) {
            return response()->json([
                'message' => 'هذا الملف موجود لديك بالفعل!',
                'existing_document_id' => $existingDocument->id,
                'existing_document_title' => $existingDocument->title,
            ], 422);
        }

        $path = $file->store('documents', 'public');

        // تحديد organization_id و department_id
        $org_id = $validated['organization_id'] ?? $user->organization_id;
        $dept_id = $validated['department_id'] ?? $user->department_id;

        // Validation: Employee و Manager يضيفان فقط في قسمهم
        if (in_array($user->role, ['Employee', 'Manager'])) {
            if ($dept_id !== $user->department_id) {
                return response()->json(['message' => 'لا يمكنك إضافة وثيقة خارج قسمك'], 403);
            }
        }

        // Validation: Admin يضيف فقط ضمن منظمته
        if ($user->role === 'Admin') {
            if ($org_id !== $user->organization_id) {
                return response()->json(['message' => 'لا يمكنك إضافة وثيقة خارج منظمتك'], 403);
            }
        }

        // Auditor لا يمكنه إضافة
        if ($user->role === 'Auditor') {
            return response()->json(['message' => 'ليس لديك صلاحية لإضافة وثائق'], 403);
        }

        $dto = new DocumentDto(
            $validated['title'],
            $validated['description'] ?? null,
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            $file->getSize(),
            $path,
            Auth::id(),
            $org_id,
            $dept_id,
        );

        return response()->json(
            $this->documents->add($dto),
            201
        );
    }

    /**
     * عرض الوثائق حسب الدور والقسم
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'SuperAdmin') {
            // عرض كل الوثائق
            return response()->json(Document::with('user', 'organization', 'department')->get());
        }

        if ($user->role === 'Admin') {
            // عرض كل الوثائق في منظمته
            return response()->json(
                Document::where('organization_id', $user->organization_id)
                    ->with('user', 'organization', 'department')
                    ->get()
            );
        }

        if ($user->role === 'Manager') {
            // عرض الوثائق في قسمه فقط
            return response()->json(
                Document::where('department_id', $user->department_id)
                    ->with('user', 'organization', 'department')
                    ->get()
            );
        }

        if ($user->role === 'Employee' || $user->role === 'Auditor') {
            // عرض الوثائق في قسمهم فقط
            return response()->json(
               Document::where('department_id', $user->department_id)
                    ->with('user', 'organization', 'department')
                    ->get()
            );
        }

        return response()->json([]);
    }

    /**
     * عرض الوثائق الخاصة به
     */
    public function myDocuments(Request $request)
    {
        $userId = Auth::id();
        return response()->json(
            Document::where('uploaded_by', $userId)
                ->with('user', 'organization', 'department')
                ->get()
        );
    }

    /**
     * عرض وثيقة واحدة
     */
    public function show($id)
    {
        $user = Auth::user();
        $doc = Document::with('user', 'organization', 'department')->find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        // التحقق من الوصول
        if ($user->role === 'SuperAdmin') {
            return response()->json($doc);
        }

        if ($user->role === 'Admin' && $doc->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك الوصول لهذه الوثيقة'], 403);
        }

        if (in_array($user->role, ['Manager', 'Employee', 'Auditor']) && $doc->department_id !== $user->department_id) {
            return response()->json(['message' => 'لا يمكنك الوصول لهذه الوثيقة'], 403);
        }

        return response()->json($doc);
    }

    /**
     * تعديل وثيقة
     * - Employee: تعديل وثائقه فقط ضمن قسمه
     * - Manager: تعديل الوثائق ضمن قسمه
     * - Admin: تعديل الوثائق ضمن منظمته
     * - SuperAdmin: تعديل أي وثيقة
     * - Auditor: لا يمكنه التعديل
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        // Auditor لا يمكنه التعديل
        if ($user->role === 'Auditor') {
            return response()->json(['message' => 'ليس لديك صلاحية لتعديل الوثائق'], 403);
        }

        // Employee يعدل فقط وثائقه
        if ($user->role === 'Employee') {
            if ($doc->uploaded_by !== $user->id || $doc->department_id !== $user->department_id) {
                return response()->json(['message' => 'لا يمكنك تعديل هذه الوثيقة'], 403);
            }
        }

        // Manager يعدل الوثائق في قسمه
        if ($user->role === 'Manager') {
            if ($doc->department_id !== $user->department_id) {
                return response()->json(['message' => 'لا يمكنك تعديل وثائق خارج قسمك'], 403);
            }
        }

        // Admin يعدل الوثائق في منظمته
        if ($user->role === 'Admin') {
            if ($doc->organization_id !== $user->organization_id) {
                return response()->json(['message' => 'لا يمكنك تعديل وثائق خارج منظمتك'], 403);
            }
        }

        $request->validate([
            'file'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $updateData = [];

        // قراءة البيانات من $_POST و $request->all() معاً للتأكد
        $allData = array_merge($_POST ?? [], $request->all());

        // تحديث العنوان
        if (!empty($allData['title'] ?? null)) {
            $updateData['title'] = trim($allData['title']);
        }

        // تحديث الوصف
        if (!empty($allData['description'] ?? null)) {
            $updateData['description'] = trim($allData['description']);
        }

        // معالجة الملف الجديد (إن وجد)
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            // حذف الملف القديم
            if ($doc->path && Storage::disk('public')->exists($doc->path)) {
                Storage::disk('public')->delete($doc->path);
            }

            // حفظ الملف الجديد
            $newPath = $file->store('documents', 'public');
            $updateData['path'] = $newPath;
            $updateData['original_name'] = $file->getClientOriginalName();
            $updateData['mime_type'] = $file->getClientMimeType();
            $updateData['size'] = $file->getSize();
        }

        // تطبيق التحديثات على النموذج مباشرة
        if (!empty($updateData)) {
            foreach ($updateData as $key => $value) {
                $doc->$key = $value;
            }
            $doc->save();
        }

        return response()->json($doc);
    }

    /**
     * حذف وثيقة
     * - Employee: لا يمكنه الحذف
     * - Manager: حذف الوثائق في قسمه
     * - Admin: حذف الوثائق في منظمته
     * - SuperAdmin: حذف أي وثيقة
     * - Auditor: لا يمكنه الحذف
     */
    public function delete($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        // Employee و Auditor لا يمكنهم الحذف
        if (in_array($user->role, ['Employee', 'Auditor'])) {
            return response()->json(['message' => 'ليس لديك صلاحية لحذف الوثائق'], 403);
        }

        // Manager يحذف الوثائق في قسمه
        if ($user->role === 'Manager') {
            if ($doc->department_id !== $user->department_id) {
                return response()->json(['message' => 'لا يمكنك حذف وثائق خارج قسمك'], 403);
            }
        }

        // Admin يحذف الوثائق في منظمته
        if ($user->role === 'Admin') {
            if ($doc->organization_id !== $user->organization_id) {
                return response()->json(['message' => 'لا يمكنك حذف وثائق خارج منظمتك'], 403);
            }
        }

        $deleted = $this->documents->delete($id);
        if (!$deleted) {
            return response()->json(['message' => 'فشل الحذف'], 500);
        }

        return response()->json(['message' => 'تم حذف الوثيقة بنجاح']);
    }
}
