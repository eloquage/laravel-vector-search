<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch;

use Eloquage\LaravelVectorSearch\Contracts\SupportsKeywordSearch;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Collection;

class VectorSearchIndex
{
    public function __construct(
        public VectorIndex $index,
        protected EmbeddingProviderManager $embeddings,
        protected VectorDriverManager $drivers,
    ) {}

    /**
     * @return Collection<int, VectorChunk>
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $embedder = $this->embeddings->forIndex($this->index);
        $driver = $this->drivers->driver($this->index->driver->value);
        $vector = $embedder->embed($query);

        return $driver->search($this->index, $vector, $limit);
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, VectorChunk>
     */
    public function keywordSearch(string $query, int $limit = 20, array $filters = []): Collection
    {
        $driver = $this->drivers->driver($this->index->driver->value);

        if (! $driver instanceof SupportsKeywordSearch) {
            return new Collection;
        }

        return $driver->keywordSearch($this->index, $query, $limit, $filters);
    }
}
