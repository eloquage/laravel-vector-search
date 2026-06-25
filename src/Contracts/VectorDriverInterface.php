<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Contracts;

use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Collection;

interface VectorDriverInterface
{
    /**
     * Upsert a chunk's vector embedding into the specific search engine.
     */
    public function upsert(VectorChunk $chunk): void;

    /**
     * Search the index for the closest chunks to the given vector.
     * Returns a collection of VectorChunk models.
     */
    public function search(VectorIndex $index, array $vector, int $limit = 5): Collection;

    /**
     * Delete a chunk from the specific search engine.
     */
    public function delete(VectorChunk $chunk): void;
}
