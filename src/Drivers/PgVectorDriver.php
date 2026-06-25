<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Drivers;

use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PgVectorDriver implements VectorDriverInterface
{
    public function __construct(
        protected ?string $connection = null,
    ) {}

    public function upsert(VectorChunk $chunk): void
    {
        if (empty($chunk->embedding)) {
            return;
        }

        if ($this->connection !== null) {
            $chunk->setConnection($this->connection);
        }

        $chunk->save();
    }

    public function search(VectorIndex $index, array $vector, int $limit = 5): Collection
    {
        $query = ($this->connection !== null ? VectorChunk::on($this->connection) : VectorChunk::query())
            ->join('vector_documents', 'vector_chunks.vector_document_id', '=', 'vector_documents.id')
            ->where('vector_documents.vector_index_id', $index->id)
            ->whereNotNull('vector_chunks.embedding')
            ->select('vector_chunks.*');

        if ($this->supportsDatabaseVectorSearch()) {
            return $query
                ->whereVectorSimilarTo('vector_chunks.embedding', $vector)
                ->limit($limit)
                ->get();
        }

        return $this->searchInMemory($query->get(), $vector, $limit);
    }

    protected function supportsDatabaseVectorSearch(): bool
    {
        return Schema::connection($this->connection)->getConnection()->getDriverName() === 'pgsql';
    }

    /**
     * @param  Collection<int, VectorChunk>  $chunks
     * @return Collection<int, VectorChunk>
     */
    protected function searchInMemory(Collection $chunks, array $vector, int $limit): Collection
    {
        return $chunks
            ->filter(fn (VectorChunk $chunk): bool => is_array($chunk->embedding) && $chunk->embedding !== [])
            ->sortBy(fn (VectorChunk $chunk): float => $this->cosineDistance($vector, $chunk->embedding))
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<float>  $left
     * @param  array<float>  $right
     */
    protected function cosineDistance(array $left, array $right): float
    {
        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;
        $length = min(count($left), count($right));

        for ($i = 0; $i < $length; $i++) {
            $dot += $left[$i] * $right[$i];
            $leftMagnitude += $left[$i] ** 2;
            $rightMagnitude += $right[$i] ** 2;
        }

        if ($leftMagnitude === 0.0 || $rightMagnitude === 0.0) {
            return 1.0;
        }

        return 1 - ($dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude)));
    }

    public function delete(VectorChunk $chunk): void
    {
        if ($this->connection !== null) {
            $chunk->setConnection($this->connection);
        }

        $chunk->update(['embedding' => null]);
    }
}
