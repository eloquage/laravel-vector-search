<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Contracts;

use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Collection;

interface VectorDriverInterface
{
    /**
     * Upsert a chunk's vector embedding into the specific search engine.
     */
    public function upsert(VectorChunk $chunk): void;

    /**
     * Upsert multiple chunks into the specific search engine.
     *
     * @param  iterable<int, VectorChunk>  $chunks
     */
    public function upsertMany(iterable $chunks): void;

    /**
     * Search the index for the closest chunks to the given vector.
     *
     * @return Collection<int, VectorChunk>
     */
    public function search(VectorIndex $index, array $vector, int $limit = 5): Collection;

    /**
     * Delete a chunk from the specific search engine.
     */
    public function delete(VectorChunk $chunk): void;

    /**
     * Delete multiple chunks from the specific search engine.
     *
     * @param  iterable<int, VectorChunk>  $chunks
     */
    public function deleteMany(iterable $chunks): void;

    /**
     * Delete all chunks belonging to a document from the specific search engine.
     */
    public function deleteByDocument(VectorDocument $document): void;
}
