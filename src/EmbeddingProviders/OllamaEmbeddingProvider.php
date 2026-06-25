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
    ) {}

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    /**
     * @return array<float>
     */
    public function embed(string $text): array
    {
        try {
            $response = Http::acceptJson()
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
}
