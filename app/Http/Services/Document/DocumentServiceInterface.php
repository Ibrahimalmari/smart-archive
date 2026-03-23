<?php

namespace App\Http\Services\Document;

use App\Http\DTOs\Document\DocumentDto;

interface DocumentServiceInterface
{
    public function add(DocumentDto $dto);

    public function list($user);

    public function get(int $id);

    public function update(int $id, array $data);

    public function delete(int $id);
}
