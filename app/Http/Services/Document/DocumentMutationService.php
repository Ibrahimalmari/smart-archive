<?php

namespace App\Http\Services\Document;

use App\Http\DTOs\Document\DocumentDto;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentMutationService
{
    public function __construct(
        private DocumentServiceInterface $documents,
        private DocumentClassificationService $classifier,
        private DocumentEmbeddingService $embeddings,
        private DocumentContentStoreInterface $contentStore,
    ) {
    }

    public function findDuplicateForUpload(User $user, UploadedFile $file): ?Document
    {
        return Document::where('uploaded_by', $user->id)
            ->where('size', $file->getSize())
            ->where('mime_type', $file->getClientMimeType())
            ->where('original_name', $file->getClientOriginalName())
            ->first();
    }

    public function createFromUpload(User $user, array $validated, UploadedFile $file): Document
    {
        return $this->persistUploadedDocument($user, $validated, $file);
    }

    public function persistUploadedDocument(
        User $user,
        array $validated,
        UploadedFile $file,
        ?string $path = null
    ): Document
    {
        $path ??= $file->store('documents', 'public');
        $organizationId = $validated['organization_id'] ?? $user->organization_id;
        $departmentId = $validated['department_id'] ?? $user->department_id;

        $classification = $this->classifier->classify(
            title: $validated['title'],
            description: $validated['description'] ?? null,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getClientMimeType(),
            extractedText: null,
        );

        $document = $this->documents->add(new DocumentDto(
            $validated['title'],
            $validated['description'] ?? null,
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            $file->getSize(),
            $path,
            $user->id,
            $organizationId,
            $departmentId,
            $classification['document_type'],
            $classification['classification_confidence'],
            $classification['classification_source'],
        ));

        $this->embeddings->syncDocumentQuietly($document);

        return $document;
    }

    public function updateDocument(Document $document, array $input, ?UploadedFile $file = null): Document
    {
        $updateData = [];
        $title = trim((string) ($input['title'] ?? ''));
        $descriptionProvided = array_key_exists('description', $input);
        $description = $descriptionProvided ? trim((string) ($input['description'] ?? '')) : null;

        if ($title !== '') {
            $updateData['title'] = $title;
        }

        if ($descriptionProvided) {
            $updateData['description'] = $description === '' ? null : $description;
        }

        $oldPath = $document->path;
        $newPath = null;

        if ($file !== null) {
            $newPath = $file->store('documents', 'public');
            $updateData['path'] = $newPath;
            $updateData['original_name'] = $file->getClientOriginalName();
            $updateData['mime_type'] = $file->getClientMimeType();
            $updateData['size'] = $file->getSize();
        }

        if ($updateData !== []) {
            foreach ($updateData as $key => $value) {
                $document->$key = $value;
            }

            $classification = $this->classifier->classify(
                title: (string) $document->title,
                description: $document->description,
                originalName: (string) $document->original_name,
                mimeType: $document->mime_type,
                extractedText: $this->contentStore->getOcrText($document),
            );

            $document->document_type = $classification['document_type'];
            $document->classification_confidence = $classification['classification_confidence'];
            $document->classification_source = $classification['classification_source'];
            $document->save();
            $this->embeddings->syncDocumentQuietly($document);
        }

        if ($newPath !== null && $oldPath && $oldPath !== $newPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return $document;
    }

    public function deleteDocument(int $id): bool
    {
        $document = Document::find($id);
        if ($document) {
            $this->contentStore->delete($document);
        }

        return $this->documents->delete($id);
    }
}
