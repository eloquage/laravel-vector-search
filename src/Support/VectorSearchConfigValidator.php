<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Support;

use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class VectorSearchConfigValidator
{
    public function __construct(
        protected Application $app,
    ) {}

    public function validate(): void
    {
        $this->validateEmbeddingProvider((string) config('vector-search.default_embedding_provider'));
        $this->validateDriver((string) config('vector-search.default_driver'));
        $this->warnWhenPgVectorIsNotPostgresql();
    }

    protected function validateEmbeddingProvider(string $provider): void
    {
        if ($provider === EmbeddingProvider::COHERE->value) {
            throw new InvalidArgumentException(
                'The Cohere embedding provider is not implemented. Use: openai, ollama, or null.'
            );
        }

        if ($provider === EmbeddingProvider::OPENAI->value && blank(config('vector-search.embedding_providers.openai.api_key'))) {
            throw new InvalidArgumentException('OpenAI embedding provider requires OPENAI_API_KEY.');
        }

        if ($provider === EmbeddingProvider::OLLAMA->value && blank(config('vector-search.embedding_providers.ollama.host'))) {
            throw new InvalidArgumentException('Ollama embedding provider requires OLLAMA_HOST.');
        }
    }

    protected function validateDriver(string $driver): void
    {
        if ($driver === IndexDriver::PINECONE->value) {
            throw new InvalidArgumentException(
                'The Pinecone driver is not implemented. Use: postgres_vector or meilisearch.'
            );
        }

        if ($driver === IndexDriver::MEILISEARCH->value && blank(config('vector-search.drivers.meilisearch.host'))) {
            throw new InvalidArgumentException('Meilisearch driver requires MEILISEARCH_HOST.');
        }
    }

    protected function warnWhenPgVectorIsNotPostgresql(): void
    {
        if (config('vector-search.default_driver') !== IndexDriver::POSTGRES_VECTOR->value) {
            return;
        }

        $connection = config('vector-search.drivers.pgvector.connection') ?? config('database.default');
        $driverName = Schema::connection($connection)->getConnection()->getDriverName();

        if ($driverName !== 'pgsql') {
            Log::warning('Vector search default driver is postgres_vector but the database connection is not PostgreSQL.', [
                'connection' => $connection,
                'driver' => $driverName,
            ]);
        }
    }
}
