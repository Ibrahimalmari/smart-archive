<?php

namespace App\Http\Services\Document;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DocumentEmbeddingService
{
    public function __construct(
        private OllamaEmbeddingClient $client,
        private OcrTextNormalizer $ocrTextNormalizer,
        private DocumentContentStoreInterface $contentStore,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function syncDocument(Document $document): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $text = $this->buildEmbeddingText($document);
        $vector = $this->client->embed([$text])[0];

        $this->contentStore->saveEmbedding(
            $document,
            $this->textHash($text),
            $this->client->model(),
            $vector
        );

        return true;
    }

    public function syncDocumentQuietly(Document $document): bool
    {
        try {
            return $this->syncDocument($document);
        } catch (\Throwable $exception) {
            Log::warning('Document embedding sync failed.', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{mode:string,results:array<int, array<string, mixed>>}
     */
    public function search(Builder $scopedQuery, string $query, int $limit = 20, string $typeFilter = ''): array
    {
        $query = trim($query);
        $mode = $this->isConfigured() ? 'hybrid_embeddings' : 'keyword_fallback';

        if ($query === '') {
            return [
                'mode' => $mode,
                'results' => [],
            ];
        }

        $normalizedQuery = $this->ocrTextNormalizer->normalize($query);
        $queryTerms = $this->queryTerms($normalizedQuery);

        if ($typeFilter !== '') {
            $scopedQuery->where('document_type', $typeFilter);
        }

        /** @var Collection<int, Document> $documents */
        $documents = $scopedQuery->get();
        if ($documents->isEmpty()) {
            return [
                'mode' => $mode,
                'results' => [],
            ];
        }

        $keywordRanked = $this->keywordRankDocuments($documents, $normalizedQuery, $queryTerms);
        if (!$this->isConfigured()) {
            return [
                'mode' => 'keyword_fallback',
                'results' => $this->sliceRanked($keywordRanked, $limit),
            ];
        }

        try {
            $this->syncStaleDocuments($documents);
            $queryVector = $this->client->embed([$normalizedQuery])[0];
        } catch (\Throwable $exception) {
            Log::warning('Embeddings search failed; falling back to keyword search.', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'mode' => 'keyword_fallback',
                'results' => $this->sliceRanked($keywordRanked, $limit),
            ];
        }

        $ranked = [];
        $minimumScore = (float) config('services.ollama.search_min_score', 0.25);

        foreach ($documents as $document) {
            $embedding = $this->contentStore->getEmbedding($document);
            $documentVector = $embedding['vector'] ?? null;
            $hasSemantic = is_array($documentVector) && $documentVector !== [];

            $semanticScore = $hasSemantic ? $this->cosineSimilarity($queryVector, $documentVector) : 0.0;
            $keywordScore = (float) ($keywordRanked[$document->id]['keyword_score'] ?? 0.0);
            $score = round(
                $hasSemantic
                    ? (($semanticScore * 0.68) + ($keywordScore * 0.32))
                    : $keywordScore,
                6
            );

            if ($score < $minimumScore && $keywordScore < 0.12) {
                continue;
            }

            $payload = $keywordRanked[$document->id] ?? $document->toArray();
            $payload['relevance_score'] = $score;
            $payload['semantic_score'] = round($semanticScore, 6);
            $payload['keyword_score'] = round($keywordScore, 6);
            $payload['search_backend'] = $hasSemantic ? 'hybrid_embeddings' : 'keyword_fallback';
            $ranked[] = $payload;
        }

        usort($ranked, function (array $left, array $right): int {
            $scoreCompare = $right['relevance_score'] <=> $left['relevance_score'];
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return $right['id'] <=> $left['id'];
        });

        if ($ranked === [] && $keywordRanked !== []) {
            return [
                'mode' => 'keyword_fallback',
                'results' => $this->sliceRanked($keywordRanked, $limit),
            ];
        }

        return [
            'mode' => 'hybrid_embeddings',
            'results' => array_slice($ranked, 0, $limit),
        ];
    }

    public function buildEmbeddingText(Document $document): string
    {
        $normalizedOcr = $this->ocrTextNormalizer->normalize($this->contentStore->getOcrText($document));
        $parts = array_filter([
            'title: ' . (string) $document->title,
            'title_focus: ' . (string) $document->title,
            'description: ' . (string) ($document->description ?? ''),
            'original_name: ' . (string) $document->original_name,
            'document_type: ' . (string) ($document->document_type ?? ''),
            'document_type_focus: ' . (string) ($document->document_type ?? ''),
            'ocr_text: ' . $this->truncate($normalizedOcr, 3000),
        ], static fn (?string $value): bool => $value !== null && trim($value) !== '');

        return implode("\n", $parts);
    }

    /**
     * @param Collection<int, Document> $documents
     */
    public function syncStaleDocuments(Collection $documents): void
    {
        $staleDocuments = $documents->filter(fn (Document $document): bool => $this->needsReindex($document))->values();
        if ($staleDocuments->isEmpty()) {
            return;
        }

        foreach ($staleDocuments->chunk(20) as $chunk) {
            $texts = [];
            foreach ($chunk as $document) {
                $texts[] = $this->buildEmbeddingText($document);
            }

            $vectors = $this->client->embed($texts);

            foreach ($chunk->values() as $index => $document) {
                $vector = $vectors[$index] ?? null;
                if (!is_array($vector) || $vector === []) {
                    continue;
                }

                $text = $texts[$index];
                $this->contentStore->saveEmbedding(
                    $document,
                    $this->textHash($text),
                    $this->client->model(),
                    $vector
                );
            }
        }
    }

    private function needsReindex(Document $document): bool
    {
        $currentHash = $this->textHash($this->buildEmbeddingText($document));
        $embedding = $this->contentStore->getEmbedding($document);
        $vector = $embedding['vector'] ?? null;

        return !is_array($vector)
            || $vector === []
            || ($embedding['text_hash'] ?? null) !== $currentHash
            || ($embedding['model'] ?? null) !== $this->client->model()
            || (int) ($embedding['dimensions'] ?? 0) !== ($this->client->dimensions() ?? 0);
    }

    /**
     * @param Collection<int, Document> $documents
     * @param array<int, string> $queryTerms
     * @return array<int, array<string, mixed>>
     */
    private function keywordRankDocuments(Collection $documents, string $normalizedQuery, array $queryTerms): array
    {
        $ranked = [];

        foreach ($documents as $document) {
            $details = $this->keywordScoreDetails($document, $queryTerms, $normalizedQuery);
            if ($details['keyword_score'] <= 0.0) {
                continue;
            }

            $payload = $document->toArray();
            $payload['keyword_score'] = round($details['keyword_score'], 6);
            $payload['semantic_score'] = 0.0;
            $payload['relevance_score'] = round($details['keyword_score'], 6);
            $payload['matched_terms'] = $details['matched_terms'];
            $payload['search_backend'] = 'keyword_fallback';
            $ranked[$document->id] = $payload;
        }

        return $ranked;
    }

    /**
     * @param array<int, string> $queryTerms
     * @return array{keyword_score:float,matched_terms:array<int,string>}
     */
    private function keywordScoreDetails(Document $document, array $queryTerms, string $normalizedQuery): array
    {
        if ($queryTerms === [] && $normalizedQuery === '') {
            return [
                'keyword_score' => 0.0,
                'matched_terms' => [],
            ];
        }

        $fields = [
            ['weight' => 0.34, 'text' => $this->ocrTextNormalizer->normalize((string) $document->title)],
            ['weight' => 0.16, 'text' => $this->ocrTextNormalizer->normalize((string) ($document->description ?? ''))],
            ['weight' => 0.10, 'text' => $this->ocrTextNormalizer->normalize((string) $document->original_name)],
            ['weight' => 0.16, 'text' => $this->ocrTextNormalizer->normalize((string) ($document->document_type ?? ''))],
            ['weight' => 0.24, 'text' => $this->ocrTextNormalizer->normalize($this->contentStore->getOcrText($document))],
        ];

        $score = 0.0;
        $matchedTerms = [];

        foreach ($fields as $field) {
            $fieldText = $field['text'];
            $weight = (float) $field['weight'];
            if ($fieldText === '') {
                continue;
            }

            $fieldMatches = [];
            foreach ($queryTerms as $term) {
                if (str_contains($fieldText, $term)) {
                    $fieldMatches[] = $term;
                }
            }

            if ($normalizedQuery !== '' && str_contains($fieldText, $normalizedQuery)) {
                $score += $weight * 0.55;
            }

            if ($fieldMatches !== []) {
                $matchedTerms = array_merge($matchedTerms, $fieldMatches);
                $score += $weight * (count($fieldMatches) / max(1, count($queryTerms)));
            }
        }

        $matchedTerms = array_values(array_unique($matchedTerms));

        return [
            'keyword_score' => min(1.0, round($score, 6)),
            'matched_terms' => $matchedTerms,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $ranked
     * @return array<int, array<string, mixed>>
     */
    private function sliceRanked(array $ranked, int $limit): array
    {
        $values = array_values($ranked);

        usort($values, function (array $left, array $right): int {
            $leftScore = $left['relevance_score'] ?? $left['keyword_score'] ?? 0;
            $rightScore = $right['relevance_score'] ?? $right['keyword_score'] ?? 0;
            $scoreCompare = $rightScore <=> $leftScore;
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return ($right['id'] ?? 0) <=> ($left['id'] ?? 0);
        });

        return array_slice($values, 0, $limit);
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        $length = min(count($left), count($right));
        if ($length === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        for ($index = 0; $index < $length; $index++) {
            $leftValue = (float) $left[$index];
            $rightValue = (float) $right[$index];
            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue * $leftValue;
            $rightNorm += $rightValue * $rightValue;
        }

        if ($leftNorm <= 0 || $rightNorm <= 0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }

    private function textHash(string $text): string
    {
        return hash('sha256', $text);
    }

    private function truncate(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit);
    }

    /**
     * @return array<int, string>
     */
    private function queryTerms(string $query): array
    {
        $parts = preg_split('/\s+/u', mb_strtolower($query)) ?: [];

        $terms = array_values(array_filter($parts, static function (string $part): bool {
            return $part !== '' && mb_strlen($part) >= 2;
        }));

        return array_values(array_unique($terms));
    }
}
