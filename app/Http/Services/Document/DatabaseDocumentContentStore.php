<?php

namespace App\Http\Services\Document;

use App\Models\Document;
use App\Models\DocumentContent;

class DatabaseDocumentContentStore implements DocumentContentStoreInterface
{
    public function driver(): string
    {
        return 'database';
    }

    public function getOcrText(Document $document): ?string
    {
        return $this->findContent($document)?->ocr_text;
    }

    public function saveOcrText(Document $document, string $text, array $metadata = []): void
    {
        $content = $this->findContent($document) ?? new DocumentContent([
            'document_id' => $document->id,
        ]);

        $content->ocr_text = $text;

        if ($metadata !== []) {
            $content->ocr_metadata = array_merge(
                is_array($content->ocr_metadata) ? $content->ocr_metadata : [],
                $metadata,
            );
        }

        $content->save();
        $document->setRelation('content', $content);
    }

    public function getEmbedding(Document $document): ?array
    {
        $content = $this->findContent($document);

        return [
            'text_hash' => $content?->embedding_text_hash,
            'model' => $content?->embedding_model,
            'dimensions' => $content?->embedding_dimensions,
            'vector' => is_array($content?->embedding_vector)
                ? array_map(static fn ($value): float => (float) $value, $content->embedding_vector)
                : null,
            'indexed_at' => $content?->embedding_indexed_at,
        ];
    }

    public function saveEmbedding(Document $document, string $textHash, string $model, array $vector): void
    {
        $content = $this->findContent($document) ?? new DocumentContent([
            'document_id' => $document->id,
        ]);

        $content->embedding_text_hash = $textHash;
        $content->embedding_model = $model;
        $content->embedding_dimensions = count($vector);
        $content->embedding_vector = array_values($vector);
        $content->embedding_indexed_at = now();
        $content->save();

        $document->setRelation('content', $content);
    }

    public function delete(Document $document): void
    {
        $document->content()->delete();
        $document->unsetRelation('content');
    }

    private function findContent(Document $document): ?DocumentContent
    {
        if ($document->relationLoaded('content')) {
            /** @var DocumentContent|null $content */
            $content = $document->getRelation('content');

            return $content;
        }

        $content = $document->content()->first();
        if ($content !== null) {
            $document->setRelation('content', $content);
        }

        return $content;
    }
}
