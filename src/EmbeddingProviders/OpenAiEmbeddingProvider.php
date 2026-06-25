<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\EmbeddingProviders;

use Eloquage\LaravelVectorSearch\Contracts\EmbeddingProviderInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model,
        protected int $batchSize = 100,
        protected int $timeout = 60,
    ) {}

    /**
     * @return array<float>
     */
    public function embed(string $text): array
    {
        $embeddings = $this->embedBatch([$text]);

        return $embeddings[0] ?? [];
    }

    /**
     * @return array<float>
     */
    public function embedQuery(string $text): array
    {
        return $this->embed($text);
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

        $results = [];

        foreach (array_chunk($texts, max(1, $this->batchSize)) as $batch) {
            $results = array_merge($results, $this->requestBatch($batch));
        }

        return $results;
    }

    public function dimensions(): ?int
    {
        return null;
    }

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    protected function requestBatch(array $texts): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $texts,
                ])
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
}
