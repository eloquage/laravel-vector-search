<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Managers;

use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Drivers\MeilisearchDriver;
use Eloquage\LaravelVectorSearch\Drivers\PgVectorDriver;
use Eloquage\LaravelVectorSearch\Drivers\PineconeDriver;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Illuminate\Support\Manager;
use Meilisearch\Client;
use RuntimeException;

class VectorDriverManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) config('vector-search.default_driver', IndexDriver::POSTGRES_VECTOR->value);
    }

    protected function createDriver($driver): VectorDriverInterface
    {
        return match ($driver) {
            IndexDriver::POSTGRES_VECTOR->value, 'pgvector' => $this->createPostgresVectorDriver(),
            IndexDriver::MEILISEARCH->value => $this->createMeilisearchDriver(),
            IndexDriver::PINECONE->value => $this->createPineconeDriver(),
            default => parent::createDriver($driver),
        };
    }

    public function createPostgresVectorDriver(): VectorDriverInterface
    {
        return new PgVectorDriver(
            connection: config('vector-search.drivers.pgvector.connection'),
        );
    }

    public function createMeilisearchDriver(): VectorDriverInterface
    {
        if (! class_exists(Client::class)) {
            throw new RuntimeException(
                'The Meilisearch driver requires meilisearch/meilisearch-php. Run: composer require meilisearch/meilisearch-php'
            );
        }

        return new MeilisearchDriver(
            host: (string) config('vector-search.drivers.meilisearch.host'),
            key: config('vector-search.drivers.meilisearch.key'),
        );
    }

    public function createPineconeDriver(): VectorDriverInterface
    {
        $config = config('vector-search.drivers.pinecone', []);

        return new PineconeDriver(
            apiKey: (string) ($config['api_key'] ?? ''),
            indexHost: (string) ($config['index_host'] ?? ''),
            timeout: (int) config('vector-search.embedding_timeout', 60),
        );
    }
}
