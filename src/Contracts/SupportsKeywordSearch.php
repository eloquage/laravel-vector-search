<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Contracts;

use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Collection;

interface SupportsKeywordSearch
{
    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, VectorChunk>
     */
    public function keywordSearch(VectorIndex $index, string $query, int $limit, array $filters = []): Collection;
}
