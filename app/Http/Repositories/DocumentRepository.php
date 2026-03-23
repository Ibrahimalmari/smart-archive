<?php

namespace App\Http\Repositories;

use App\Http\DTOs\Document\DocumentDto;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class DocumentRepository implements DocumentRepositoryInterface
{
    public function create(DocumentDto $dto)
    {
        return Document::create([
            'title'         => $dto->title,
            'description'   => $dto->description,
            'original_name' => $dto->originalName,
            'mime_type'     => $dto->mimeType,
            'path'          => $dto->path,
            'size'          => $dto->size,
            'uploaded_by'   => $dto->userId,            
            'organization_id' => $dto->organizationId,
            'department_id'   => $dto->departmentId,
        ]);
    }

    public function getAllForUser($user)
    {
        if ($user->role === 'Admin') {
            return Document::with('user','organization','department')->get();
        }

        if ($user->role === 'Manager') {
            return Document::with('user','organization','department')
                ->whereHas('user', function($q) {
                    $q->where('role', 'Employee');
                })->get();
        }

        return Document::with('user','organization','department')
                ->where('uploaded_by', $user->id)->get();
    }

    public function getById(int $id)
    {
        return Document::with('user','organization','department')->find($id);
    }

    public function update(int $id, array $data)
    {
        $doc = Document::find($id);
        if (!$doc) return null;

        $doc->update($data);
        return $doc;
    }

    public function delete(Document $document)
    {
        if ($document->path && Storage::disk('public')->exists($document->path)) {
            Storage::disk('public')->delete($document->path);
        }

        return $document->delete();
    }
}
