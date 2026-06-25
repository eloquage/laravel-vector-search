<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Managers;

use Eloquage\LaravelVectorSearch\Contracts\ConfigurableEmbeddingProvider;
use Eloquage\LaravelVectorSearch\Contracts\EmbeddingProviderInterface;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\NullEmbeddingProvider;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\OllamaEmbeddingProvider;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\OpenAiEmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class EmbeddingProviderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) config('vector-search.default_embedding_provider', EmbeddingProvider::OPENAI->value);
    }

    public function forIndex(VectorIndex $index): EmbeddingProviderInterface
    {
        $provider = $this->driver($index->embedding_provider->value);

        if (! $provider instanceof ConfigurableEmbeddingProvider) {
            return $provider;
        }

        return $provider
            ->withModel($index->embedding_model)
            ->withDimensions($index->dimensions);
    }

    protected function createDriver($driver): EmbeddingProviderInterface
    {
        if (isset($this->customCreators[$driver])) {
            return parent::createDriver($driver);
        }

        return match ($driver) {
            EmbeddingProvider::OPENAI->value => $this->createOpenaiDriver(),
            EmbeddingProvider::COHERE->value => $this->createCohereDriver(),
            EmbeddingProvider::OLLAMA->value => $this->createOllamaDriver(),
            EmbeddingProvider::NULL_PROVIDER->value, 'null' => $this->createNullDriver(),
            default => parent::createDriver($driver),
        };
    }

    public function createOpenaiDriver(): EmbeddingProviderInterface
    {
        $config = config('vector-search.embedding_providers.openai', []);

        return new OpenAiEmbeddingProvider(
            apiKey: (string) ($config['api_key'] ?? ''),
            model: (string) ($config['model'] ?? 'text-embedding-3-small'),
        );
    }

    public function createCohereDriver(): EmbeddingProviderInterface
    {
        throw new InvalidArgumentException('The Cohere embedding provider is not implemented yet.');
    }

    public function createOllamaDriver(): EmbeddingProviderInterface
    {
        $config = config('vector-search.embedding_providers.ollama', []);

        return new OllamaEmbeddingProvider(
            host: (string) ($config['host'] ?? 'http://localhost:11434'),
            model: (string) ($config['model'] ?? 'nomic-embed-text'),
        );
    }

    public function createNullDriver(): EmbeddingProviderInterface
    {
        return new NullEmbeddingProvider;
    }
}
