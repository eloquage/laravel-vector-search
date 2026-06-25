<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\EmbeddingProviders;

use Eloquage\LaravelVectorSearch\Contracts\EmbeddingProviderInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CohereEmbeddingProvider implements EmbeddingProviderInterface
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
        return $this->requestEmbeddings([$text], 'search_document')[0] ?? [];
    }

    /**
     * @return array<float>
     */
    public function embedQuery(string $text): array
    {
        return $this->requestEmbeddings([$text], 'search_query')[0] ?? [];
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
            throw new RuntimeException('Cohere API key is not configured.');
        }

        $results = [];

        foreach (array_chunk($texts, max(1, $this->batchSize)) as $batch) {
            $results = array_merge($results, $this->requestEmbeddings($batch, 'search_document'));
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
    protected function requestEmbeddings(array $texts, string $inputType): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post('https://api.cohere.com/v2/embed', [
                    'model' => $this->model,
                    'texts' => $texts,
                    'input_type' => $inputType,
                    'embedding_types' => ['float'],
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Cohere embedding request failed: '.$exception->getMessage(),
                previous: $exception
            );
        }

        /** @var array<int, array<float>> $embeddings */
        $embeddings = $response->json('embeddings.float', []);

        return $embeddings;
    }
}
