<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\EmbeddingProviders;

use Eloquage\LaravelVectorSearch\Contracts\EmbeddingProviderInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        protected string $host,
        protected string $model,
        protected int $concurrency = 4,
        protected int $timeout = 60,
    ) {}

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        if ($this->concurrency <= 1 || count($texts) === 1) {
            return array_map(fn (string $text): array => $this->embed($text), $texts);
        }

        $results = array_fill(0, count($texts), []);
        $chunks = array_chunk(array_keys($texts), max(1, $this->concurrency));

        foreach ($chunks as $indexChunk) {
            $responses = Http::pool(function ($pool) use ($indexChunk, $texts): void {
                foreach ($indexChunk as $index) {
                    $pool->as((string) $index)
                        ->acceptJson()
                        ->timeout($this->timeout)
                        ->post(rtrim($this->host, '/').'/api/embeddings', [
                            'model' => $this->model,
                            'prompt' => $texts[$index],
                        ]);
                }
            });

            foreach ($indexChunk as $index) {
                $response = $responses[(string) $index] ?? null;

                if ($response === null || $response->failed()) {
                    throw new RuntimeException('Ollama embedding request failed.');
                }

                /** @var array<float> $embedding */
                $embedding = $response->json('embedding', []);
                $results[$index] = $embedding;
            }
        }

        return $results;
    }

    /**
     * @return array<float>
     */
    public function embed(string $text): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout($this->timeout)
                ->post(rtrim($this->host, '/').'/api/embeddings', [
                    'model' => $this->model,
                    'prompt' => $text,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Ollama embedding request failed: '.$exception->getMessage(),
                previous: $exception
            );
        }

        /** @var array<float> $embedding */
        $embedding = $response->json('embedding', []);

        return $embedding;
    }

    /**
     * @return array<float>
     */
    public function embedQuery(string $text): array
    {
        return $this->embed($text);
    }

    public function dimensions(): ?int
    {
        return null;
    }
}
