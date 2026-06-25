<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\EmbeddingProviders;

use Eloquage\LaravelVectorSearch\Contracts\ConfigurableEmbeddingProvider;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaEmbeddingProvider implements ConfigurableEmbeddingProvider
{
    public function __construct(
        protected string $host,
        protected string $model,
        protected ?int $dimensions = null,
    ) {}

    public function withModel(string $model): static
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    public function withDimensions(?int $dimensions): static
    {
        $clone = clone $this;
        $clone->dimensions = $dimensions;

        return $clone;
    }

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $concurrency = (int) config('vector-search.embedding_providers.ollama.concurrency', 4);
        $results = array_fill(0, count($texts), []);

        foreach (array_chunk($texts, max(1, $concurrency), true) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch): void {
                foreach ($batch as $index => $text) {
                    $pool->as((string) $index)
                        ->acceptJson()
                        ->timeout((int) config('vector-search.embedding_providers.ollama.timeout', 60))
                        ->connectTimeout(10)
                        ->retry(3, 500, fn ($exception): bool => $exception instanceof RequestException && $exception->response !== null && $exception->response->status() === 429)
                        ->post(rtrim($this->host, '/').'/api/embeddings', [
                            'model' => $this->model,
                            'prompt' => $text,
                        ]);
                }
            });

            foreach ($batch as $index => $text) {
                $response = $responses[(string) $index] ?? null;

                if ($response === null) {
                    throw new RuntimeException('Ollama embedding request failed: missing response.');
                }

                try {
                    $response->throw();
                } catch (RequestException $exception) {
                    throw new RuntimeException(
                        'Ollama embedding request failed: '.$exception->getMessage(),
                        previous: $exception
                    );
                }

                /** @var array<float> $embedding */
                $embedding = $response->json('embedding', []);
                $results[$index] = $embedding;
            }
        }

        return array_values($results);
    }

    /**
     * @return array<float>
     */
    public function embed(string $text): array
    {
        $embeddings = $this->embedBatch([$text]);

        return $embeddings[0] ?? [];
    }
}
