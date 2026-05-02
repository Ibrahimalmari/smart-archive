<?php

namespace App\Http\Services\Document;

use App\Models\Document;
use RuntimeException;

class MongoDocumentContentStore implements DocumentContentStoreInterface
{
    public function driver(): string
    {
        return 'mongodb';
    }

    public function isAvailable(): bool
    {
        return class_exists(\MongoDB\Driver\Manager::class)
            && $this->uri() !== ''
            && $this->database() !== ''
            && $this->collection() !== '';
    }

    public function getOcrText(Document $document): ?string
    {
        $content = $this->findContent($document);

        return $content['ocr']['text'] ?? null;
    }

    public function saveOcrText(Document $document, string $text, array $metadata = []): void
    {
        $now = $this->now();
        $bulk = new \MongoDB\Driver\BulkWrite();

        $bulk->update(
            ['document_id' => (int) $document->id],
            [
                '$set' => [
                    'document_id' => (int) $document->id,
                    'ocr' => [
                        'text' => $text,
                        'metadata' => $metadata,
                        'updated_at' => $now,
                    ],
                    'updated_at' => $now,
                ],
                '$setOnInsert' => [
                    'created_at' => $now,
                ],
            ],
            ['upsert' => true]
        );

        $this->manager()->executeBulkWrite($this->namespace(), $bulk);
    }

    public function getEmbedding(Document $document): ?array
    {
        $content = $this->findContent($document);
        $embedding = $content['embedding'] ?? null;

        if (!is_array($embedding)) {
            return null;
        }

        return [
            'text_hash' => $embedding['text_hash'] ?? null,
            'model' => $embedding['model'] ?? null,
            'dimensions' => $embedding['dimensions'] ?? null,
            'vector' => is_array($embedding['vector'] ?? null)
                ? array_map(static fn ($value): float => (float) $value, $embedding['vector'])
                : null,
            'indexed_at' => $embedding['indexed_at'] ?? null,
        ];
    }

    public function saveEmbedding(Document $document, string $textHash, string $model, array $vector): void
    {
        $now = $this->now();
        $bulk = new \MongoDB\Driver\BulkWrite();

        $bulk->update(
            ['document_id' => (int) $document->id],
            [
                '$set' => [
                    'document_id' => (int) $document->id,
                    'embedding' => [
                        'text_hash' => $textHash,
                        'model' => $model,
                        'dimensions' => count($vector),
                        'vector' => array_values($vector),
                        'indexed_at' => $now,
                    ],
                    'updated_at' => $now,
                ],
                '$setOnInsert' => [
                    'created_at' => $now,
                ],
            ],
            ['upsert' => true]
        );

        $this->manager()->executeBulkWrite($this->namespace(), $bulk);
    }

    public function delete(Document $document): void
    {
        $bulk = new \MongoDB\Driver\BulkWrite();
        $bulk->delete(['document_id' => (int) $document->id], ['limit' => 0]);

        $this->manager()->executeBulkWrite($this->namespace(), $bulk);
    }

    private function findContent(Document $document): ?array
    {
        $query = new \MongoDB\Driver\Query(
            ['document_id' => (int) $document->id],
            ['limit' => 1]
        );

        $cursor = $this->manager()->executeQuery($this->namespace(), $query);

        foreach ($cursor as $item) {
            return $this->toArray($item);
        }

        return null;
    }

    private function manager(): \MongoDB\Driver\Manager
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('MongoDB content store is not available. Install/enable ext-mongodb and configure DOCUMENT_CONTENT_MONGODB_* values.');
        }

        return new \MongoDB\Driver\Manager($this->uri());
    }

    private function namespace(): string
    {
        return $this->database() . '.' . $this->collection();
    }

    private function uri(): string
    {
        return trim((string) config('services.document_content.mongodb_uri', ''));
    }

    private function database(): string
    {
        return trim((string) config('services.document_content.mongodb_database', ''));
    }

    private function collection(): string
    {
        return trim((string) config('services.document_content.mongodb_collection', ''));
    }

    private function now(): mixed
    {
        if (class_exists(\MongoDB\BSON\UTCDateTime::class)) {
            return new \MongoDB\BSON\UTCDateTime((int) (microtime(true) * 1000));
        }

        return now()->toIso8601String();
    }

    private function toArray(mixed $value): array
    {
        return json_decode((string) json_encode($value), true) ?: [];
    }
}
