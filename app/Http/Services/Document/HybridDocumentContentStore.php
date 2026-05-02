<?php

namespace App\Http\Services\Document;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Throwable;

class HybridDocumentContentStore implements DocumentContentStoreInterface
{
    public function __construct(
        private DatabaseDocumentContentStore $database,
        private MongoDocumentContentStore $mongo,
    ) {
    }

    public function driver(): string
    {
        return $this->shouldUseMongo() ? $this->mongo->driver() : $this->database->driver();
    }

    public function getOcrText(Document $document): ?string
    {
        return $this->run(
            fn (): ?string => $this->mongo->getOcrText($document) ?? $this->database->getOcrText($document),
            fn (): ?string => $this->database->getOcrText($document),
        );
    }

    public function saveOcrText(Document $document, string $text, array $metadata = []): void
    {
        $this->run(
            fn (): bool => $this->tapMongo(function () use ($document, $text, $metadata): void {
                $this->mongo->saveOcrText($document, $text, $metadata);
            }),
            fn (): bool => $this->tapDatabase(function () use ($document, $text, $metadata): void {
                $this->database->saveOcrText($document, $text, $metadata);
            }),
        );
    }

    public function getEmbedding(Document $document): ?array
    {
        return $this->run(
            fn (): ?array => $this->mongo->getEmbedding($document) ?? $this->database->getEmbedding($document),
            fn (): ?array => $this->database->getEmbedding($document),
        );
    }

    public function saveEmbedding(Document $document, string $textHash, string $model, array $vector): void
    {
        $this->run(
            fn (): bool => $this->tapMongo(function () use ($document, $textHash, $model, $vector): void {
                $this->mongo->saveEmbedding($document, $textHash, $model, $vector);
            }),
            fn (): bool => $this->tapDatabase(function () use ($document, $textHash, $model, $vector): void {
                $this->database->saveEmbedding($document, $textHash, $model, $vector);
            }),
        );
    }

    public function delete(Document $document): void
    {
        $this->run(
            fn (): bool => $this->tapMongo(fn (): null => $this->mongo->delete($document)),
            fn (): bool => $this->tapDatabase(fn (): null => $this->database->delete($document)),
        );
    }

    private function shouldUseMongo(): bool
    {
        return config('services.document_content.driver', 'database') === 'mongodb'
            && $this->mongo->isAvailable();
    }

    private function run(callable $mongoOperation, callable $databaseOperation): mixed
    {
        if (!$this->shouldUseMongo()) {
            return $databaseOperation();
        }

        try {
            return $mongoOperation();
        } catch (Throwable $exception) {
            Log::warning('MongoDB document content store failed; falling back to database.', [
                'error' => $exception->getMessage(),
            ]);

            if ((bool) config('services.document_content.strict', false)) {
                throw $exception;
            }

            return $databaseOperation();
        }
    }

    private function tapMongo(callable $callback): bool
    {
        $callback();

        return true;
    }

    private function tapDatabase(callable $callback): bool
    {
        $callback();

        return true;
    }
}
