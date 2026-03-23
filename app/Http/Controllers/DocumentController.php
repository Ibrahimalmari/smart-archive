<?php

namespace App\Http\Controllers;

use App\Http\Services\Document\DocumentServiceInterface;
use App\Http\DTOs\Document\DocumentDto;
use App\Http\Requests\StoreDocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

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

    /**
     * تحميل وثيقة
     * - نفس قواعد الوصول مثل show()
     */
    public function download($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        // التحقق من الوصول
        if ($user->role === 'SuperAdmin') {
            // يمكنه تحميل أي وثيقة
        } elseif ($user->role === 'Admin' && $doc->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك تحميل هذه الوثيقة'], 403);
        } elseif (in_array($user->role, ['Manager', 'Employee', 'Auditor']) && $doc->department_id !== $user->department_id) {
            return response()->json(['message' => 'لا يمكنك تحميل هذه الوثيقة'], 403);
        }

        // التحقق من وجود الملف
        if (!$doc->path || !Storage::disk('public')->exists($doc->path)) {
            return response()->json(['message' => 'الملف غير موجود على الخادم'], 404);
        }

        // إرجاع الملف للتحميل
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        return response()->download($disk->path($doc->path), $doc->original_name);
    }

    /**
     * عرض وثيقة (إرجاع رابط للعرض)
     * - نفس قواعد الوصول مثل show()
     */
    public function view($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        // التحقق من الوصول
        if ($user->role === 'SuperAdmin') {
            // يمكنه عرض أي وثيقة
        } elseif ($user->role === 'Admin' && $doc->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك عرض هذه الوثيقة'], 403);
        } elseif (in_array($user->role, ['Manager', 'Employee', 'Auditor']) && $doc->department_id !== $user->department_id) {
            return response()->json(['message' => 'لا يمكنك عرض هذه الوثيقة'], 403);
        }

        // التحقق من وجود الملف
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        if (!$doc->path || !$disk->exists($doc->path)) {
            return response()->json(['message' => 'الملف غير موجود على الخادم'], 404);
        }

        // إرجاع رابط للعرض
        $url = $disk->url($doc->path);

        return response()->json([
            'document' => $doc,
            'view_url' => $url
        ]);
    }

    /**
     * البحث في الوثائق
     * - البحث في العنوان والوصف
     * - فلترة حسب الدور
     */
    public function search(Request $request)
    {
        $user = Auth::user();
        $query = $request->get('q', '');
        $limit = $request->get('limit', 20);

        if (empty($query)) {
            return response()->json(['message' => 'يجب تحديد كلمة البحث'], 400);
        }

        $documentsQuery = Document::with('user', 'organization', 'department')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('original_name', 'like', "%{$query}%");
            });

        // تطبيق الفلاتر حسب الدور
        if ($user->role === 'SuperAdmin') {
            // لا قيود
        } elseif ($user->role === 'Admin') {
            $documentsQuery->where('organization_id', $user->organization_id);
        } elseif (in_array($user->role, ['Manager', 'Employee', 'Auditor'])) {
            $documentsQuery->where('department_id', $user->department_id);
        }

        $documents = $documentsQuery->limit($limit)->get();

        return response()->json([
            'query' => $query,
            'results' => $documents,
            'count' => $documents->count()
        ]);
    }

    /**
     * استخراج النص من الوثيقة باستخدام OCR
     * - يدعم PDF و الصور
     */
    public function extractOcr($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        // التحقق من الوصول
        if ($user->role === 'SuperAdmin') {
            // يمكنه استخراج OCR من أي وثيقة
        } elseif ($user->role === 'Admin' && $doc->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك الوصول لهذه الوثيقة'], 403);
        } elseif (in_array($user->role, ['Manager', 'Employee', 'Auditor']) && $doc->department_id !== $user->department_id) {
            return response()->json(['message' => 'لا يمكنك الوصول لهذه الوثيقة'], 403);
        }

        // التحقق من وجود الملف
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        if (!$doc->path || !$disk->exists($doc->path)) {
            return response()->json(['message' => 'الملف غير موجود على الخادم'], 404);
        }

        try {
            // جرب Tesseract من PATH ثم من مسار ثابت
            $tesseractExecutable = env('TESSERACT_PATH', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe');
            $tesseractVersion = null;

            if (file_exists($tesseractExecutable)) {
                $tesseractVersion = shell_exec('"' . $tesseractExecutable . '" --version 2>&1');
            } else {
                $tesseractVersion = shell_exec('tesseract --version 2>&1');
                if ($tesseractVersion) {
                    $tesseractExecutable = 'tesseract';
                }
            }

            if (!$tesseractVersion || stripos($tesseractVersion, 'tesseract') === false) {
                return response()->json([
                    'message' => 'Tesseract OCR غير مثبت أو غير متاح في بيئة PHP',
                    'error' => 'يجب تثبيت Tesseract OCR و/أو تحديث PATH',
                    'install_instructions' => [
                        '1. اذهب إلى: https://github.com/UB-Mannheim/tesseract/wiki',
                        '2. ثبت Tesseract ثم أضف C:\\Program Files\\Tesseract-OCR إلى PATH',
                        '3. تأكد أن الأمر tesseract يعمل في cmd/powershell',
                        '4. أعد تشغيل الخادم وبيئة التطوير',
                        '5. يمكن استعمال المتغير TESSERACT_PATH في .env لتحديد المسار مباشرة'
                    ],
                    'download_link' => 'https://github.com/UB-Mannheim/tesseract/wiki',
                    'technical_error' => trim($tesseractVersion ?? 'غير معروف')
                ], 503);
            }

            $filePath = $disk->path($doc->path);
            $tempImage = null;

            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($fileExtension === 'pdf') {

                try {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($filePath);

                    $extractedText = trim($pdf->getText());

                    if (empty($extractedText)) {
                        return response()->json([
                            'message' => 'هذا PDF عبارة عن صورة (Scan)',
                            'note' => 'يجب استخدام OCR له (سأعلمك لاحقًا)'
                        ], 422);
                    }

                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'فشل في قراءة PDF',
                        'error' => $e->getMessage()
                    ], 500);
                }

            } else {

                // الصور فقط → OCR
                $ocr = new TesseractOCR($filePath);
                $ocr->executable($tesseractExecutable);
                $ocr->lang('ara+eng');

                $extractedText = $ocr->run();
            }

            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($fileExtension === 'pdf') {

                try {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($filePath);

                    $extractedText = trim($pdf->getText());

                    if (empty($extractedText)) {
                        return response()->json([
                            'message' => 'هذا PDF عبارة عن صورة (Scan)',
                            'note' => 'حاليًا النظام يدعم فقط PDF النصي'
                        ], 422);
                    }

                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'فشل في قراءة PDF',
                        'error' => $e->getMessage()
                    ], 500);
                }

            } else {

                // الصور فقط → OCR
                $ocr = new TesseractOCR($filePath);
                $ocr->executable($tesseractExecutable);
                $ocr->lang('ara+eng');

                $extractedText = $ocr->run();
            }

            // حذف الملف المؤقت إذا أنشأناه
            if ($tempImage && file_exists($tempImage)) {
                @unlink($tempImage);
            }

            // حفظ النص المستخرج في قاعدة البيانات
            $doc->extracted_text = $extractedText;
            $doc->save();

            return response()->json([
                'document_id' => $doc->id,
                'extracted_text' => $extractedText,
                'message' => 'تم استخراج النص بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشل في استخراج النص',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض النص المستخرج من الوثيقة
     */
    public function getOcrText($id)
    {
        $user = Auth::user();
        $doc = Document::with('user', 'organization', 'department')->find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        // التحقق من الوصول
        if ($user->role === 'SuperAdmin') {
            // يمكنه عرض أي وثيقة
        } elseif ($user->role === 'Admin' && $doc->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك الوصول لهذه الوثيقة'], 403);
        } elseif (in_array($user->role, ['Manager', 'Employee', 'Auditor']) && $doc->department_id !== $user->department_id) {
            return response()->json(['message' => 'لا يمكنك الوصول لهذه الوثيقة'], 403);
        }

        return response()->json([
            'document' => $doc,
            'extracted_text' => $doc->extracted_text ?? 'لم يتم استخراج نص بعد'
        ]);
    }
}
