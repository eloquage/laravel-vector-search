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
    ) {
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

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
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

        /** @var array<int, array{embedding: array<float>}> $data */
        $data = $response->json('data', []);

        usort($data, fn(array $left, array $right): int => ($left['index'] ?? 0) <=> ($right['index'] ?? 0));

        return array_map(
            fn(array $item): array => $item['embedding'],
            $data,
        );
    }
}
