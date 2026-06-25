<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Support;

use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Meilisearch\Client;
use RuntimeException;

class VectorSearchConfigValidator
{
    public function __construct(
        private EmbeddingProviderManager $embeddings,
    ) {}

    public function validate(): void
    {
        $issues = $this->collectIssues();

        if ($issues === []) {
            return;
        }

        $strict = (bool) config('vector-search.strict_boot_validation', false);

        foreach ($issues as $issue) {
            if ($strict) {
                throw new RuntimeException($issue);
            }

            Log::warning($issue, ['package' => 'laravel-vector-search']);
        }
    }

    /**
     * @return list<string>
     */
    public function collectIssues(): array
    {
        $issues = [];

        $defaultProvider = (string) config('vector-search.default_embedding_provider', 'openai');

        if (! $this->embeddings->isConfigured($defaultProvider)) {
            $issues[] = "Default embedding provider [{$defaultProvider}] is not configured.";
        }

        $defaultDriver = (string) config('vector-search.default_driver', IndexDriver::POSTGRES_VECTOR->value);

        if ($defaultDriver === IndexDriver::MEILISEARCH->value && ! class_exists(Client::class)) {
            $issues[] = 'Default driver is meilisearch but meilisearch/meilisearch-php is not installed.';
        }

        if (in_array($defaultDriver, [IndexDriver::POSTGRES_VECTOR->value, 'pgvector'], true)) {
            $connection = config('vector-search.drivers.pgvector.connection');
            $driverName = Schema::connection($connection)->getConnection()->getDriverName();

            if ($driverName !== 'pgsql') {
                $issues[] = 'Default driver is postgres_vector but the configured database connection is not pgsql.';
            }
        }

        return $issues;
    }
}
