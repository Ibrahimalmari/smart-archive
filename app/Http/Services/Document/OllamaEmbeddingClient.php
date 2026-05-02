<?php

namespace App\Http\Services\Document;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaEmbeddingClient
{
    public function isConfigured(): bool
    {
        return $this->model() !== '' && $this->baseUrl() !== '';
    }

    public function model(): string
    {
        return trim((string) config('services.ollama.embedding_model', 'nomic-embed-text'));
    }

    public function dimensions(): ?int
    {
        $value = (int) config('services.ollama.embedding_dimensions', 0);

        return $value > 0 ? $value : null;
    }

    /**
     * @param array<int, string> $inputs
     * @return array<int, array<int, float>>
     */
    public function embed(array $inputs): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Ollama embeddings are not configured. Set OLLAMA_BASE_URL and OLLAMA_EMBEDDING_MODEL first.');
        }

        $payload = [
            'model' => $this->model(),
            'input' => array_values($inputs),
        ];

        $response = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout((int) config('services.ollama.timeout', 30))
            ->post('/api/embed', $payload);

        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Ollama embeddings request failed: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        $embeddings = $response->json('embeddings');
        if (!is_array($embeddings) || count($embeddings) !== count($inputs)) {
            throw new RuntimeException('Unexpected Ollama embeddings response payload.');
        }

        $vectors = [];
        foreach ($embeddings as $embedding) {
            if (!is_array($embedding) || $embedding === []) {
                throw new RuntimeException('Embedding vector missing from Ollama response.');
            }

            $vectors[] = array_map(static fn ($value): float => (float) $value, $embedding);
        }

        return $vectors;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.ollama.base_url', 'http://localhost:11434'), '/');
    }
}
