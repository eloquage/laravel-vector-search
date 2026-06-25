<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\EmbeddingProviders;

use Eloquage\LaravelVectorSearch\Contracts\ConfigurableEmbeddingProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiEmbeddingProvider implements ConfigurableEmbeddingProvider
{
    public function __construct(
        protected string $apiKey,
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
     * @return array<float>
     */
    public function embed(string $text): array
    {
        $embeddings = $this->embedBatch([$text]);

        return $embeddings[0] ?? [];
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

        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $batchSize = (int) config('vector-search.embedding_providers.openai.batch_size', 100);
        $results = [];

        foreach (array_chunk($texts, max(1, $batchSize)) as $batch) {
            $results = array_merge($results, $this->requestEmbeddings($batch));
        }

        return $results;
    }

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    protected function requestEmbeddings(array $texts): array
    {
        $timeout = (int) config('vector-search.embedding_providers.openai.timeout', 60);

        $payload = [
            'model' => $this->model,
            'input' => $texts,
        ];

        if ($this->dimensions !== null) {
            $payload['dimensions'] = $this->dimensions;
        }

        try {
            $response = $this->httpClient($timeout)
                ->post('https://api.openai.com/v1/embeddings', $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'OpenAI embedding request failed: '.$exception->getMessage(),
                previous: $exception
            );
        }

        /** @var array<int, array{embedding: array<float>, index?: int}> $data */
        $data = $response->json('data', []);

        usort($data, fn (array $left, array $right): int => ($left['index'] ?? 0) <=> ($right['index'] ?? 0));

        return array_map(
            fn (array $item): array => $item['embedding'],
            $data,
        );
    }

    protected function httpClient(int $timeout): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout(10)
            ->retry(3, 500, fn ($exception): bool => $exception instanceof RequestException && $exception->response !== null && $exception->response->status() === 429);
    }
}
