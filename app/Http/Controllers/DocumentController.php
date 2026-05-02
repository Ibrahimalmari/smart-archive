<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Services\Document\DocumentAccessService;
use App\Http\Services\Document\DocumentClassificationService;
use App\Http\Services\Document\DocumentContentStoreInterface;
use App\Http\Services\Document\DocumentEmbeddingService;
use App\Http\Services\Document\DocumentMutationService;
use App\Http\Services\Document\DocumentWorkflowService;
use App\Http\Services\Document\DocumentWorkflowTransitionException;
use App\Http\Services\Document\OcrTextNormalizer;
use App\Models\Document;
use App\Models\DocumentApprovalLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentController extends Controller
{
    private DocumentAccessService $documentAccess;
    private DocumentMutationService $documentMutations;
    private DocumentWorkflowService $documentWorkflow;
    private DocumentClassificationService $classifier;
    private DocumentEmbeddingService $embeddings;
    private DocumentContentStoreInterface $contentStore;
    private OcrTextNormalizer $ocrTextNormalizer;

    public function __construct(
        DocumentAccessService $documentAccess,
        DocumentMutationService $documentMutations,
        DocumentWorkflowService $documentWorkflow,
        DocumentClassificationService $classifier,
        DocumentEmbeddingService $embeddings,
        DocumentContentStoreInterface $contentStore,
        OcrTextNormalizer $ocrTextNormalizer
    ) {
        $this->documentAccess = $documentAccess;
        $this->documentMutations = $documentMutations;
        $this->documentWorkflow = $documentWorkflow;
        $this->classifier = $classifier;
        $this->embeddings = $embeddings;
        $this->contentStore = $contentStore;
        $this->ocrTextNormalizer = $ocrTextNormalizer;
    }

    public function add(StoreDocumentRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $file = $request->file('file');
        $orgId = $request->input('organization_id', $user->organization_id);
        $deptId = $request->input('department_id', $user->department_id);

        if (!$this->documentAccess->canCreate($user, $orgId, $deptId)) {
            return response()->json(['message' => $this->createDeniedMessage($user)], 403);
        }

        $existingDocument = $this->documentMutations->findDuplicateForUpload($user, $file);

        if ($existingDocument) {
            return response()->json([
                'message' => 'هذا الملف موجود لديك بالفعل!',
                'existing_document_id' => $existingDocument->id,
                'existing_document_title' => $existingDocument->title,
            ], 422);
        }

        if (
            (in_array($user->role, ['Employee', 'Manager'], true) && (int) $deptId !== (int) $user->department_id)
            || ($user->role === 'Admin' && (int) $orgId !== (int) $user->organization_id)
            || $user->role === 'Auditor'
        ) {
            return response()->json(['message' => $this->createDeniedMessage($user)], 403);
        }

        $path = $file->store('documents', 'public');

        $orgId = $validated['organization_id'] ?? $user->organization_id;
        $deptId = $validated['department_id'] ?? $user->department_id;

        if (in_array($user->role, ['Employee', 'Manager'], true) && $deptId !== $user->department_id) {
            return response()->json(['message' => 'لا يمكنك إضافة وثيقة خارج قسمك'], 403);
        }

        if ($user->role === 'Admin' && $orgId !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك إضافة وثيقة خارج منظمتك'], 403);
        }

        if ($user->role === 'Auditor') {
            return response()->json(['message' => 'ليس لديك صلاحية لإضافة وثائق'], 403);
        }

        $document = $this->documentMutations->persistUploadedDocument($user, $validated, $file, $path);

        return response()->json($document, 201);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        return response()->json($this->documentAccess->scopedQuery($user)->get());
    }

    public function myDocuments(Request $request)
    {
        $userId = Auth::id();

        return response()->json(
            Document::where('uploaded_by', $userId)
                ->with('user', 'organization', 'department')
                ->get()
        );
    }

    public function show($id)
    {
        $user = Auth::user();
        $doc = Document::with('user', 'organization', 'department')->find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'لا يمكنك الوصول لهذه الوثيقة'], 403);
        }

        $ocrText = $this->contentStore->getOcrText($doc);

        return response()->json(array_merge(
            $doc->toArray(),
            ['extracted_text' => $ocrText],
            $this->ocrDisplayPayload($ocrText)
        ));
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if ($doc && !$this->documentAccess->canUpdate($user, $doc)) {
            return response()->json(['message' => $this->updateDeniedMessage($user)], 403);
        }

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        if ($user->role === 'Auditor') {
            return response()->json(['message' => 'ليس لديك صلاحية لتعديل الوثائق'], 403);
        }

        if ($user->role === 'Employee') {
            if ($doc->uploaded_by !== $user->id || $doc->department_id !== $user->department_id) {
                return response()->json(['message' => 'لا يمكنك تعديل هذه الوثيقة'], 403);
            }
        }

        if ($user->role === 'Manager' && $doc->department_id !== $user->department_id) {
            return response()->json(['message' => 'لا يمكنك تعديل وثائق خارج قسمك'], 403);
        }

        if ($user->role === 'Admin' && $doc->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك تعديل وثائق خارج منظمتك'], 403);
        }

        if (!in_array($doc->status, [Document::STATUS_PENDING, Document::STATUS_REJECTED], true)) {
            return response()->json([
                'message' => 'Only pending or rejected documents can be updated.',
                'current_status' => $doc->status,
            ], 422);
        }

        $request->validate([
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $document = $this->documentMutations->updateDocument(
            $doc,
            array_merge($_POST ?? [], $request->all()),
            $request->file('file')
        );

        return response()->json($document);
    }

    public function delete($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if ($doc && !$this->documentAccess->canDelete($user, $doc)) {
            return response()->json(['message' => $this->deleteDeniedMessage($user)], 403);
        }

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        if (in_array($user->role, ['Employee', 'Auditor'], true)) {
            return response()->json(['message' => 'ليس لديك صلاحية لحذف الوثائق'], 403);
        }

        if ($user->role === 'Manager' && $doc->department_id !== $user->department_id) {
            return response()->json(['message' => 'لا يمكنك حذف وثائق خارج قسمك'], 403);
        }

        if ($user->role === 'Admin' && $doc->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'لا يمكنك حذف وثائق خارج منظمتك'], 403);
        }

        if (!in_array($doc->status, [Document::STATUS_PENDING, Document::STATUS_REJECTED], true)) {
            return response()->json([
                'message' => 'Only pending or rejected documents can be deleted.',
                'current_status' => $doc->status,
            ], 422);
        }

        $deleted = $this->documentMutations->deleteDocument($id);
        if (!$deleted) {
            return response()->json(['message' => 'فشل الحذف'], 500);
        }

        return response()->json(['message' => 'تم حذف الوثيقة بنجاح']);
    }

    public function download($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'لا يمكنك تحميل هذه الوثيقة'], 403);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        if (!$doc->path || !$disk->exists($doc->path)) {
            return response()->json(['message' => 'الملف غير موجود على الخادم'], 404);
        }

        return response()->download($disk->path($doc->path), $doc->original_name);
    }

    public function view($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'لا يمكنك عرض هذه الوثيقة'], 403);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        if (!$doc->path || !$disk->exists($doc->path)) {
            return response()->json(['message' => 'الملف غير موجود على الخادم'], 404);
        }

        $url = $disk->url($doc->path);
        $ocrText = $this->contentStore->getOcrText($doc);

        return response()->json([
            'document' => $doc,
            'view_url' => $url,
            'extracted_text' => $ocrText,
            ...$this->ocrDisplayPayload($ocrText),
        ]);
    }

    public function search(Request $request)
    {
        $user = Auth::user();
        $query = trim((string) $request->get('q', ''));
        $typeFilter = trim((string) $request->get('document_type', ''));
        $limit = max(1, min((int) $request->get('limit', 20), 100));

        if ($query === '') {
            return response()->json(['message' => 'يجب تحديد كلمة البحث'], 400);
        }

        $search = $this->embeddings->search(
            $this->documentAccess->scopedQuery($user),
            $query,
            $limit,
            $typeFilter
        );

        return response()->json([
            'query' => $query,
            'search_mode' => $search['mode'],
            'results' => $search['results'],
            'count' => count($search['results']),
        ]);
    }

    public function extractOcr($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'الوثيقة غير موجودة'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'لا يمكنك الوصول لهذه الوثيقة'], 403);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        if (!$doc->path || !$disk->exists($doc->path)) {
            return response()->json(['message' => 'الملف غير موجود على الخادم'], 404);
        }

        try {
            $tesseractExecutable = $this->resolveTesseractExecutable();
            if ($tesseractExecutable === null) {
                $pdftoppmExecutable = $this->resolvePdftoppmExecutable();
                return response()->json([
                    'message' => 'Tesseract OCR غير مثبت أو غير متاح في بيئة PHP',
                    'error' => 'يجب تثبيت Tesseract OCR وتحديث PATH أو TESSERACT_PATH',
                    'diagnostics' => [
                        'tesseract' => false,
                        'imagick' => class_exists('Imagick'),
                        'pdftoppm' => $pdftoppmExecutable !== null,
                        'magick' => $this->commandExists('magick'),
                    ],
                ], 503);
            }

            $filePath = $disk->path($doc->path);
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $converter = null;

            if ($fileExtension === 'pdf') {
                [$images, $tempDir, $converter] = $this->convertPdfToImages($filePath);

                if (empty($images)) {
                    $this->cleanupTempDirectory($tempDir);
                    $pdftoppmExecutable = $this->resolvePdftoppmExecutable();

                    return response()->json([
                        'message' => 'فشل تحويل PDF إلى صور لاستخراج OCR',
                        'error' => 'ثبت Imagick+Ghostscript أو Poppler (pdftoppm) أو ImageMagick (magick).',
                        'diagnostics' => [
                            'tesseract' => true,
                            'imagick' => class_exists('Imagick'),
                            'pdftoppm' => $pdftoppmExecutable !== null,
                            'magick' => $this->commandExists('magick'),
                        ],
                    ], 503);
                }

                try {
                    $extractedText = $this->runOcrOnImages($images, $tesseractExecutable);
                } finally {
                    $this->cleanupTempDirectory($tempDir);
                }
            } else {
                $extractedText = $this->runOcrOnImage($filePath, $tesseractExecutable);
            }

            $extractedText = $this->ocrTextNormalizer->normalize((string) $extractedText);
            if ($extractedText === '') {
                return response()->json([
                    'message' => 'OCR لم يتمكن من استخراج نص مفيد من الوثيقة',
                ], 422);
            }

            $this->contentStore->saveOcrText($doc, $extractedText, [
                'engine' => 'tesseract',
                'pdf_converter' => $converter,
            ]);

            $classification = $this->classifier->classify(
                title: (string) $doc->title,
                description: $doc->description,
                originalName: (string) $doc->original_name,
                mimeType: $doc->mime_type,
                extractedText: $extractedText,
            );

            $doc->document_type = $classification['document_type'];
            $doc->classification_confidence = $classification['classification_confidence'];
            $doc->classification_source = $classification['classification_source'];
            $doc->save();
            $this->embeddings->syncDocumentQuietly($doc);

            return response()->json([
                'document_id' => $doc->id,
                'extracted_text' => $extractedText,
                ...$this->ocrDisplayPayload($extractedText),
                'content_store' => $this->contentStore->driver(),
                'pdf_converter' => $converter,
                'message' => 'تم استخراج النص بنجاح',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل في استخراج النص',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOcrText($id)
    {
        $user = Auth::user();
        $doc = Document::with('user', 'organization', 'department')->find($id);

        if (!$doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'You cannot access this document'], 403);
        }

        $storedText = $this->contentStore->getOcrText($doc);
        $normalizedText = $this->ocrTextNormalizer->normalize($storedText);
        if ($normalizedText !== '' && $normalizedText !== (string) $storedText) {
            $this->contentStore->saveOcrText($doc, $normalizedText, [
                'normalized_by' => 'OcrTextNormalizer',
            ]);
            $this->embeddings->syncDocumentQuietly($doc);
        }

        return response()->json([
            'document' => $doc,
            'extracted_text' => $normalizedText !== '' ? $normalizedText : 'No OCR text extracted yet',
            'content_store' => $this->contentStore->driver(),
            ...$this->ocrDisplayPayload($normalizedText),
        ]);
    }

    public function submitForReview(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'You cannot access this document'], 403);
        }

        if (!$this->documentAccess->canSubmitForReview($user, $doc)) {
            return response()->json(['message' => 'You are not allowed to submit this document for review'], 403);
        }

        try {
            $document = $this->documentWorkflow->submit(
                $doc,
                $user,
                $request->input('notes'),
            );
        } catch (DocumentWorkflowTransitionException $exception) {
            return $this->transitionNotAllowedResponse(
                $doc,
                $exception->allowedStatuses,
                $exception->action
            );
        }

        return response()->json([
            'message' => 'Document submitted for review',
            'document' => $document,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'approval_notes' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'You cannot access this document'], 403);
        }

        if (!$this->documentAccess->canReviewWorkflow($user, $doc)) {
            return response()->json(['message' => 'You are not allowed to approve this document'], 403);
        }

        try {
            $document = $this->documentWorkflow->approve(
                $doc,
                $user,
                $request->input('approval_notes'),
            );
        } catch (DocumentWorkflowTransitionException $exception) {
            return $this->transitionNotAllowedResponse(
                $doc,
                $exception->allowedStatuses,
                $exception->action
            );
        }

        return response()->json([
            'message' => 'Document approved',
            'document' => $document,
        ]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'You cannot access this document'], 403);
        }

        if (!$this->documentAccess->canReviewWorkflow($user, $doc)) {
            return response()->json(['message' => 'You are not allowed to reject this document'], 403);
        }

        try {
            $document = $this->documentWorkflow->reject(
                $doc,
                $user,
                trim((string) $request->input('rejection_reason')),
            );
        } catch (DocumentWorkflowTransitionException $exception) {
            return $this->transitionNotAllowedResponse(
                $doc,
                $exception->allowedStatuses,
                $exception->action
            );
        }

        return response()->json([
            'message' => 'Document rejected',
            'document' => $document,
        ]);
    }

    public function archive(Request $request, $id)
    {
        $request->validate([
            'archive_reason' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'You cannot access this document'], 403);
        }

        if (!$this->documentAccess->canReviewWorkflow($user, $doc)) {
            return response()->json(['message' => 'You are not allowed to archive this document'], 403);
        }

        try {
            $document = $this->documentWorkflow->archive(
                $doc,
                $user,
                $request->input('archive_reason'),
            );
        } catch (DocumentWorkflowTransitionException $exception) {
            return $this->transitionNotAllowedResponse(
                $doc,
                $exception->allowedStatuses,
                $exception->action
            );
        }

        return response()->json([
            'message' => 'Document archived',
            'document' => $document,
        ]);
    }

    public function reopen(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'You cannot access this document'], 403);
        }

        if (!$this->documentAccess->canReviewWorkflow($user, $doc)) {
            return response()->json(['message' => 'You are not allowed to reopen this document'], 403);
        }

        try {
            $document = $this->documentWorkflow->reopen(
                $doc,
                $user,
                $request->input('notes'),
            );
        } catch (DocumentWorkflowTransitionException $exception) {
            return $this->transitionNotAllowedResponse(
                $doc,
                $exception->allowedStatuses,
                $exception->action
            );
        }

        return response()->json([
            'message' => 'Document moved back to review',
            'document' => $document,
        ]);
    }

    public function workflowHistory($id)
    {
        $user = Auth::user();
        $doc = Document::find($id);

        if (!$doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$this->documentAccess->canAccess($user, $doc)) {
            return response()->json(['message' => 'You cannot access this document'], 403);
        }

        $history = DocumentApprovalLog::where('document_id', $doc->id)
            ->with('actor:id,name,email,role')
            ->orderBy('id')
            ->get();

        return response()->json([
            'document' => $doc->fresh($this->documentWorkflow->relations()),
            'history' => $history,
        ]);
    }

    private function transitionNotAllowedResponse(Document $doc, array $allowed, string $action)
    {
        return response()->json([
            'message' => "Invalid status transition for {$action}",
            'current_status' => $doc->status,
            'allowed_statuses' => $allowed,
        ], 422);
    }

    private function createDeniedMessage($user): string
    {
        return match ($user->role) {
            'Auditor' => 'ليس لديك صلاحية لإضافة وثائق',
            'Admin' => 'لا يمكنك إضافة وثيقة خارج منظمتك',
            'Employee', 'Manager' => 'لا يمكنك إضافة وثيقة خارج قسمك',
            default => 'ليس لديك صلاحية لإضافة وثائق',
        };
    }

    private function updateDeniedMessage($user): string
    {
        return match ($user->role) {
            'Auditor' => 'ليس لديك صلاحية لتعديل الوثائق',
            'Manager' => 'لا يمكنك تعديل وثائق خارج قسمك',
            'Admin' => 'لا يمكنك تعديل وثائق خارج منظمتك',
            default => 'لا يمكنك تعديل هذه الوثيقة',
        };
    }

    private function deleteDeniedMessage($user): string
    {
        return match ($user->role) {
            'Employee', 'Auditor' => 'ليس لديك صلاحية لحذف الوثائق',
            'Manager' => 'لا يمكنك حذف وثائق خارج قسمك',
            'Admin' => 'لا يمكنك حذف وثائق خارج منظمتك',
            default => 'لا يمكنك حذف هذه الوثيقة',
        };
    }

    private function resolveTesseractExecutable(): ?string
    {
        $fromEnv = env('TESSERACT_PATH');
        if (!empty($fromEnv) && file_exists($fromEnv) && $this->isTesseractAvailable($fromEnv)) {
            return $fromEnv;
        }

        if ($this->isTesseractAvailable('tesseract')) {
            return 'tesseract';
        }

        $defaultWindowsPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        if (file_exists($defaultWindowsPath) && $this->isTesseractAvailable($defaultWindowsPath)) {
            return $defaultWindowsPath;
        }

        return null;
    }

    private function isTesseractAvailable(string $binary): bool
    {
        $command = $binary === 'tesseract'
            ? 'tesseract --version 2>&1'
            : escapeshellarg($binary) . ' --version 2>&1';

        $output = shell_exec($command);
        return is_string($output) && stripos($output, 'tesseract') !== false;
    }

    private function convertPdfToImages(string $pdfPath): array
    {
        $tempDir = storage_path('app/tmp/ocr_' . str_replace('.', '', uniqid('', true)));
        if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new \RuntimeException('Unable to create temporary OCR directory.');
        }

        if (class_exists('Imagick')) {
            try {
                $images = $this->convertPdfWithImagick($pdfPath, $tempDir);
                if (!empty($images)) {
                    return [$images, $tempDir, 'imagick'];
                }
            } catch (\Throwable $e) {
                // Fallback to command-line converters
            }
        }

        $pdftoppmExecutable = $this->resolvePdftoppmExecutable();
        if ($pdftoppmExecutable !== null) {
            $images = $this->convertPdfWithPdftoppm($pdfPath, $tempDir, $pdftoppmExecutable);
            if (!empty($images)) {
                return [$images, $tempDir, 'pdftoppm'];
            }
        }

        if ($this->commandExists('magick')) {
            $images = $this->convertPdfWithMagick($pdfPath, $tempDir);
            if (!empty($images)) {
                return [$images, $tempDir, 'magick'];
            }
        }

        return [[], $tempDir, null];
    }

    private function convertPdfWithImagick(string $pdfPath, string $tempDir): array
    {
        $images = [];

        $imagickClass = 'Imagick';
        $imagick = new $imagickClass();
        $imagick->setResolution(300, 300);
        $imagick->readImage($pdfPath);

        $index = 0;
        foreach ($imagick as $page) {
            $page->setImageFormat('png');
            $page->setImageCompressionQuality(100);

            $outputPath = $tempDir . DIRECTORY_SEPARATOR . sprintf('page-%03d.png', $index);
            $page->writeImage($outputPath);
            $images[] = $outputPath;
            $index++;
        }

        $imagick->clear();
        $imagick->destroy();

        sort($images);
        return $images;
    }

    private function convertPdfWithPdftoppm(string $pdfPath, string $tempDir, string $pdftoppmExecutable): array
    {
        $prefix = $tempDir . DIRECTORY_SEPARATOR . 'page';
        $command = escapeshellarg($pdftoppmExecutable) . ' -png -r 300 ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($prefix) . ' 2>&1';
        shell_exec($command);

        $images = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
        sort($images);

        return $images;
    }

    private function convertPdfWithMagick(string $pdfPath, string $tempDir): array
    {
        $pattern = $tempDir . DIRECTORY_SEPARATOR . 'page-%03d.png';
        $command = 'magick -density 300 ' . escapeshellarg($pdfPath) . ' -quality 100 ' . escapeshellarg($pattern) . ' 2>&1';
        shell_exec($command);

        $images = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
        sort($images);

        return $images;
    }

    private function runOcrOnImages(array $imagePaths, string $tesseractExecutable): string
    {
        $chunks = [];

        foreach ($imagePaths as $imagePath) {
            $text = trim($this->runOcrOnImage($imagePath, $tesseractExecutable));
            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        return implode(PHP_EOL . PHP_EOL, $chunks);
    }

    private function runOcrOnImage(string $imagePath, string $tesseractExecutable): string
    {
        $candidates = [];
        foreach ([6, 4, 11] as $psm) {
            $candidate = $this->runSingleOcrPass($imagePath, $tesseractExecutable, $psm);
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            return '';
        }

        usort($candidates, fn (string $left, string $right): int => $this->scoreOcrCandidate($right) <=> $this->scoreOcrCandidate($left));

        return $candidates[0];
    }

    private function runSingleOcrPass(string $imagePath, string $tesseractExecutable, int $psm): string
    {
        $ocr = new TesseractOCR($imagePath);
        $ocr->executable($tesseractExecutable);
        $ocr->lang('ara+eng');
        $ocr->oem(1);
        $ocr->psm($psm);
        $ocr->preserve_interword_spaces('1');
        $ocr->user_defined_dpi('300');
        $ocr->load_system_dawg('0');
        $ocr->load_freq_dawg('0');
        $ocr->textord_heavy_nr('1');

        $tessdataDir = $this->resolveTessdataDirectory($tesseractExecutable);
        if ($tessdataDir !== null) {
            $ocr->tessdataDir($tessdataDir);
        }

        return $this->ocrTextNormalizer->normalize((string) $ocr->run());
    }

    private function scoreOcrCandidate(string $text): int
    {
        preg_match_all('/\p{Arabic}/u', $text, $arabicMatches);
        preg_match_all('/[A-Za-z]/u', $text, $latinMatches);
        preg_match_all('/\d/u', $text, $digitMatches);
        preg_match_all('/[^\p{Arabic}A-Za-z0-9\s\.\,\:\;\-\(\)\/]/u', $text, $noiseMatches);

        $arabicCount = count($arabicMatches[0]);
        $latinCount = count($latinMatches[0]);
        $digitCount = count($digitMatches[0]);
        $noiseCount = count($noiseMatches[0]);

        $lineCount = max(1, substr_count($text, "\n") + 1);
        $lengthScore = min(mb_strlen($text), 2000);

        return ($arabicCount * 5) + ($latinCount * 2) + $digitCount + $lengthScore + ($lineCount * 3) - ($noiseCount * 8);
    }

    private function resolveTessdataDirectory(string $tesseractExecutable): ?string
    {
        $fromEnv = env('TESSDATA_PREFIX');
        if (!empty($fromEnv) && is_dir($fromEnv)) {
            return $fromEnv;
        }

        if ($tesseractExecutable !== 'tesseract') {
            $candidate = dirname($tesseractExecutable) . DIRECTORY_SEPARATOR . 'tessdata';
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        $defaultWindowsPath = 'C:\\Program Files\\Tesseract-OCR\\tessdata';
        if (is_dir($defaultWindowsPath)) {
            return $defaultWindowsPath;
        }

        return null;
    }

    private function commandExists(string $command): bool
    {
        $checkCommand = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? 'where ' . $command . ' 2>NUL'
            : 'command -v ' . $command . ' 2>/dev/null';

        $output = shell_exec($checkCommand);
        return !empty(trim((string) $output));
    }

    private function resolvePdftoppmExecutable(): ?string
    {
        $fromEnv = env('PDFTOPPM_PATH');
        if (!empty($fromEnv) && file_exists($fromEnv)) {
            return $fromEnv;
        }

        if ($this->commandExists('pdftoppm')) {
            return 'pdftoppm';
        }

        $localAppData = (string) getenv('LOCALAPPDATA');
        $packagesRoot = rtrim($localAppData, '\\/') . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'WinGet' . DIRECTORY_SEPARATOR . 'Packages';

        if (is_dir($packagesRoot)) {
            $packageDirs = glob($packagesRoot . DIRECTORY_SEPARATOR . 'oschwartz10612.Poppler_*') ?: [];

            foreach ($packageDirs as $packageDir) {
                try {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($packageDir, \FilesystemIterator::SKIP_DOTS)
                    );

                    foreach ($iterator as $file) {
                        /** @var \SplFileInfo $file */
                        if ($file->isFile() && strtolower($file->getFilename()) === 'pdftoppm.exe') {
                            return $file->getPathname();
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore inaccessible package directories.
                }
            }
        }

        return null;
    }

    private function cleanupTempDirectory(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            return;
        }

        $files = glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($tempDir);
    }

    private function ocrDisplayPayload(?string $text): array
    {
        $normalized = $this->ocrTextNormalizer->normalize($text);

        return [
            'extracted_text_normalized' => $normalized === '' ? null : $normalized,
            'extracted_text_display' => $normalized === '' ? null : $this->ocrTextNormalizer->formatForDisplay($normalized),
            'extracted_text_direction' => $normalized === '' ? 'auto' : $this->ocrTextNormalizer->direction($normalized),
        ];
    }
}

