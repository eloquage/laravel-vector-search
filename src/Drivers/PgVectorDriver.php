<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Drivers;

use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Eloquage\LaravelVectorSearch\Support\CosineSimilarity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PgVectorDriver implements VectorDriverInterface
{
    protected static bool $databaseSearchWarningLogged = false;

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

    public function upsertBatch(Collection $chunks): void
    {
        foreach ($chunks as $chunk) {
            $this->upsert($chunk);
        }
    }

    public function search(VectorIndex $index, array $vector, int $limit = 5): Collection
    {
        $query = $this->baseChunkQuery($index);

        if ($this->supportsDatabaseVectorSearch()) {
            return $query
                ->whereVectorSimilarTo('vector_chunks.embedding', $vector)
                ->limit($limit)
                ->get();
        }

        if (! self::$databaseSearchWarningLogged) {
            Log::warning('PgVector database search unavailable; falling back to in-memory similarity.');

            self::$databaseSearchWarningLogged = true;
        }

        return $this->searchInMemory($query->get(), $vector, $limit);
    }

    /**
     * @return Builder<VectorChunk>
     */
    protected function baseChunkQuery(VectorIndex $index): Builder
    {
        return ($this->connection !== null ? VectorChunk::on($this->connection) : VectorChunk::query())
            ->join('vector_documents', 'vector_chunks.vector_document_id', '=', 'vector_documents.id')
            ->join('vector_indexes', 'vector_documents.vector_index_id', '=', 'vector_indexes.id')
            ->where('vector_documents.vector_index_id', $index->id)
            ->whereNull('vector_documents.deleted_at')
            ->whereNull('vector_indexes.deleted_at')
            ->whereNotNull('vector_chunks.embedding')
            ->select('vector_chunks.*');
    }

    protected function supportsDatabaseVectorSearch(): bool
    {
        $connection = Schema::connection($this->connection)->getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return false;
        }

        if (! method_exists(VectorChunk::query(), 'whereVectorSimilarTo')) {
            return false;
        }

        try {
            $result = $connection->select(
                "SELECT 1 FROM pg_extension WHERE extname = 'vector' LIMIT 1"
            );

            return $result !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  Collection<int, VectorChunk>  $chunks
     * @return Collection<int, VectorChunk>
     */
    protected function searchInMemory(Collection $chunks, array $vector, int $limit): Collection
    {
        return $chunks
            ->filter(fn (VectorChunk $chunk): bool => is_array($chunk->embedding) && $chunk->embedding !== [])
            ->sortBy(fn (VectorChunk $chunk): float => CosineSimilarity::distance($vector, $chunk->embedding))
            ->take($limit)
            ->values();
    }

    public function delete(VectorChunk $chunk): void
    {
        if ($this->connection !== null) {
            $chunk->setConnection($this->connection);
        }

        $chunk->update(['embedding' => null]);
    }
}
