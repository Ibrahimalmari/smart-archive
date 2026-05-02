<?php

namespace App\Http\Services\Document;

use App\Models\Document;

interface DocumentContentStoreInterface
{
    public function driver(): string;

    public function getOcrText(Document $document): ?string;

    /**
     * @param array<string, mixed> $metadata
     */
    public function saveOcrText(Document $document, string $text, array $metadata = []): void;

    /**
     * @return array{text_hash:?string,model:?string,dimensions:?int,vector:?array,indexed_at:mixed}|null
     */
    public function getEmbedding(Document $document): ?array;

    /**
     * @param array<int, float> $vector
     */
    public function saveEmbedding(Document $document, string $textHash, string $model, array $vector): void;

    public function delete(Document $document): void;
}
