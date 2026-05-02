<?php

use App\Http\Services\Document\DocumentClassificationService;
use App\Http\Services\Document\DatabaseDocumentContentStore;
use App\Http\Services\Document\DocumentContentStoreInterface;
use App\Http\Services\Document\DocumentEmbeddingService;
use App\Http\Services\Document\OcrTextNormalizer;
use App\Models\Document;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('documents:reindex-embeddings {--chunk=20}', function () {
    /** @var DocumentEmbeddingService $embeddings */
    $embeddings = app(DocumentEmbeddingService::class);

    if (!$embeddings->isConfigured()) {
        $this->error('OLLAMA_BASE_URL or OLLAMA_EMBEDDING_MODEL is not configured.');
        return self::FAILURE;
    }

    $chunkSize = max(1, (int) $this->option('chunk'));
    $count = 0;

    Document::query()
        ->orderBy('id')
        ->chunk($chunkSize, function ($documents) use ($embeddings, &$count) {
            $embeddings->syncStaleDocuments($documents);
            $count += $documents->count();
            $this->info("Indexed {$count} documents...");
        });

    $this->info('Document embeddings reindex completed.');

    return self::SUCCESS;
})->purpose('Generate Ollama embedding vectors for all documents.');

Artisan::command('documents:refresh-ocr-metadata {--chunk=50}', function () {
    /** @var OcrTextNormalizer $normalizer */
    $normalizer = app(OcrTextNormalizer::class);
    /** @var DocumentClassificationService $classifier */
    $classifier = app(DocumentClassificationService::class);
    /** @var DocumentEmbeddingService $embeddings */
    $embeddings = app(DocumentEmbeddingService::class);
    /** @var DocumentContentStoreInterface $contentStore */
    $contentStore = app(DocumentContentStoreInterface::class);

    $chunkSize = max(1, (int) $this->option('chunk'));
    $processed = 0;

    Document::query()
        ->orderBy('id')
        ->chunk($chunkSize, function ($documents) use ($normalizer, $classifier, $embeddings, $contentStore, &$processed) {
            foreach ($documents as $document) {
                $storedText = $contentStore->getOcrText($document);
                $normalizedText = $normalizer->normalize($storedText);

                $classification = $classifier->classify(
                    title: (string) $document->title,
                    description: $document->description,
                    originalName: (string) $document->original_name,
                    mimeType: $document->mime_type,
                    extractedText: $normalizedText,
                );

                if ($normalizedText !== '' && $normalizedText !== (string) $storedText) {
                    $contentStore->saveOcrText($document, $normalizedText, [
                        'normalized_by' => 'OcrTextNormalizer',
                    ]);
                }

                $document->document_type = $classification['document_type'];
                $document->classification_confidence = $classification['classification_confidence'];
                $document->classification_source = $classification['classification_source'];
                $document->save();

                $embeddings->syncDocumentQuietly($document);
                $processed++;
            }

            $this->info("Refreshed {$processed} documents...");
        });

    $this->info('OCR normalization and document classification refresh completed.');

    return self::SUCCESS;
})->purpose('Normalize OCR text, refresh classification, and reindex embeddings.');

Artisan::command('documents:sync-content-store {--chunk=50}', function () {
    /** @var DatabaseDocumentContentStore $databaseStore */
    $databaseStore = app(DatabaseDocumentContentStore::class);
    /** @var DocumentContentStoreInterface $contentStore */
    $contentStore = app(DocumentContentStoreInterface::class);

    $chunkSize = max(1, (int) $this->option('chunk'));
    $processed = 0;
    $syncedOcr = 0;
    $syncedEmbeddings = 0;

    Document::query()
        ->orderBy('id')
        ->chunk($chunkSize, function ($documents) use ($databaseStore, $contentStore, &$processed, &$syncedOcr, &$syncedEmbeddings) {
            foreach ($documents as $document) {
                $ocrText = $databaseStore->getOcrText($document);
                if ($ocrText !== null && $ocrText !== '') {
                    $contentStore->saveOcrText($document, $ocrText, [
                        'synced_from' => 'document_contents',
                    ]);
                    $syncedOcr++;
                }

                $embedding = $databaseStore->getEmbedding($document);
                $vector = $embedding['vector'] ?? null;

                if (
                    is_array($vector)
                    && $vector !== []
                    && is_string($embedding['text_hash'] ?? null)
                    && $embedding['text_hash'] !== ''
                    && is_string($embedding['model'] ?? null)
                    && $embedding['model'] !== ''
                ) {
                    $contentStore->saveEmbedding(
                        $document,
                        $embedding['text_hash'],
                        $embedding['model'],
                        $vector
                    );
                    $syncedEmbeddings++;
                }

                $processed++;
            }

            $this->info("Synced {$processed} documents...");
        });

    $this->info("Content sync completed. OCR synced: {$syncedOcr}. Embeddings synced: {$syncedEmbeddings}.");

    return self::SUCCESS;
})->purpose('Sync OCR text and embeddings from SQL fallback storage into the active content store.');
