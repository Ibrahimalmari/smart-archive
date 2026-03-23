<?php

namespace App\Http\Services\Document;

use App\Http\DTOs\Document\DocumentDto;
use App\Http\Repositories\DocumentRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class DocumentService implements DocumentServiceInterface
{
    private DocumentRepositoryInterface $documents;

    public function __construct(DocumentRepositoryInterface $documents)
    {
        $this->documents = $documents;
    }

    public function add(DocumentDto $dto)
    {
        return $this->documents->create($dto);
    }

    public function list($user)
    {
        return $this->documents->getAllForUser($user);
    }

    public function get(int $id)
    {
        return $this->documents->getById($id);
    }

    public function update(int $id, array $data)
    {
        return $this->documents->update($id, $data);
    }

    public function delete(int $id)
    {
        $doc = $this->documents->getById($id);
        if (!$doc) return false;

        return $this->documents->delete($doc);
    }
}
