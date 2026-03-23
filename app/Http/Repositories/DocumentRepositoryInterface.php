<?php

namespace App\Http\Repositories;

use App\Http\DTOs\Document\DocumentDto;
use App\Models\Document;

interface DocumentRepositoryInterface
{
    public function create(DocumentDto $dto);

    public function getAllForUser($user);

    public function getById(int $id);

    public function update(int $id, array $data);

    public function delete(Document $document);
}
