<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Drivers;

use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Collection;

class MeilisearchDriver implements VectorDriverInterface
{
    public function __construct(
        protected string $host,
        protected ?string $key = null,
    ) {
    }

    public function upsert(VectorChunk $chunk): void
    {
        // Placeholder for syncing chunks to a Meilisearch index with vector search enabled.
    }

    public function search(VectorIndex $index, array $vector, int $limit = 5): Collection
    {
        return new Collection;
    }

    public function delete(VectorChunk $chunk): void
    {
        // Placeholder for removing chunk from Meilisearch.
    }
}
