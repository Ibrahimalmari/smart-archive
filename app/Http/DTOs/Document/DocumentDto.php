<?php

namespace App\Http\DTOs\Document;

class DocumentDto
{
    public string $title;
    public ?string $description;
    public string $originalName;
    public string $mimeType;
    public int $size;
    public string $path;
    public int $userId;
    public ?int $organizationId;
    public ?int $departmentId;

    public function __construct(
        string $title,
        ?string $description,
        string $originalName,
        string $mimeType,
        int $size,
        string $path,
        int $userId,
        ?int $organizationId = null,
        ?int $departmentId = null
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->path = $path;
        $this->userId = $userId;
        $this->organizationId = $organizationId;
        $this->departmentId = $departmentId;
    }
}
