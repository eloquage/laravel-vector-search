<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Managers;

use Eloquage\LaravelVectorSearch\Contracts\EmbeddingProviderInterface;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\CohereEmbeddingProvider;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\NullEmbeddingProvider;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\OllamaEmbeddingProvider;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\OpenAiEmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Illuminate\Support\Manager;
use RuntimeException;

class EmbeddingProviderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) config('vector-search.default_embedding_provider', EmbeddingProvider::OPENAI->value);
    }

    public function isConfigured(?string $driver = null): bool
    {
        $driver ??= $this->getDefaultDriver();

        return match ($driver) {
            EmbeddingProvider::NULL_PROVIDER->value, 'null', 'null_provider' => true,
            EmbeddingProvider::OPENAI->value => filled(config('vector-search.embedding_providers.openai.api_key')),
            EmbeddingProvider::OLLAMA->value => filled(config('vector-search.embedding_providers.ollama.host')),
            EmbeddingProvider::COHERE->value => filled(config('vector-search.embedding_providers.cohere.api_key')),
            'laravel-ai' => true,
            default => false,
        };
    }

    protected function createDriver($driver): EmbeddingProviderInterface
    {
        if (in_array($driver, [EmbeddingProvider::NULL_PROVIDER->value, 'null', 'null_provider'], true)) {
            $this->guardNullProvider($driver);
        }

        return match ($driver) {
            EmbeddingProvider::OPENAI->value => $this->createOpenaiDriver(),
            EmbeddingProvider::COHERE->value => $this->createCohereDriver(),
            EmbeddingProvider::OLLAMA->value => $this->createOllamaDriver(),
            EmbeddingProvider::NULL_PROVIDER->value, 'null' => $this->createNullDriver(),
            default => parent::createDriver($driver),
        };
    }

    protected function guardNullProvider(string $driver): void
    {
        if (config('vector-search.allow_null_embedding_provider', false)) {
            return;
        }

        if (app()->environment(['local', 'testing'])) {
            return;
        }

        throw new RuntimeException(
            "Null embedding provider [{$driver}] is not allowed outside local/testing environments."
        );
    }

    public function createOpenaiDriver(): EmbeddingProviderInterface
    {
        $config = config('vector-search.embedding_providers.openai', []);

        return new OpenAiEmbeddingProvider(
            apiKey: (string) ($config['api_key'] ?? ''),
            model: (string) ($config['model'] ?? 'text-embedding-3-small'),
            batchSize: (int) config('vector-search.embedding_batch_size', 100),
            timeout: (int) config('vector-search.embedding_timeout', 60),
        );
    }

    public function createCohereDriver(): EmbeddingProviderInterface
    {
        $config = config('vector-search.embedding_providers.cohere', []);

        return new CohereEmbeddingProvider(
            apiKey: (string) ($config['api_key'] ?? ''),
            model: (string) ($config['model'] ?? 'embed-english-v3.0'),
            batchSize: (int) config('vector-search.embedding_batch_size', 100),
            timeout: (int) config('vector-search.embedding_timeout', 60),
        );
    }

    public function createOllamaDriver(): EmbeddingProviderInterface
    {
        $config = config('vector-search.embedding_providers.ollama', []);

        return new OllamaEmbeddingProvider(
            host: (string) ($config['host'] ?? 'http://localhost:11434'),
            model: (string) ($config['model'] ?? 'nomic-embed-text'),
            concurrency: (int) ($config['concurrency'] ?? 4),
            timeout: (int) config('vector-search.embedding_timeout', 60),
        );
    }

    public function createNullDriver(): EmbeddingProviderInterface
    {
        return new NullEmbeddingProvider;
    }
}
