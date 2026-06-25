<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Engines;

use Eloquage\LaravelVectorSearch\Facades\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Services\VectorChunkCleanup;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class VectorSearchEngine extends Engine
{
    public function update($models): void
    {
        $models->each(function (Model $model): void {
            if (method_exists($model, 'syncVectorDocument')) {
                $model->syncVectorDocument();
            }
        });
    }

    public function search(Builder $builder): Collection
    {
        return $this->chunkHits($builder);
    }

    protected function chunkHits(Builder $builder): Collection
    {
        $indexName = $builder->index ?? config('vector-search.scout.default_index',
            config('vector-search.default_index', 'default'));

        $chunks = LaravelVectorSearch::forIndex((string) $indexName)->search(
            $builder->query,
            $builder->limit ?? 10,
        );

        if ($chunks->isEmpty()) {
            return collect();
        }

        return VectorChunk::query()
            ->whereIn('id', $chunks->pluck('id'))
            ->with('vectorDocument.source')
            ->get()
            ->map(function (VectorChunk $chunk): ?array {
                $source = $chunk->vectorDocument->source;

                if ($source === null) {
                    return null;
                }

                if (! method_exists($source, 'getScoutKey')) {
                    return null;
                }

                return [
                    'source_type' => get_class($source),
                    'source_key' => $source->getScoutKey(),
                ];
            })
            ->filter()
            ->unique('source_key')
            ->values();
    }

    /**
     * @param  Collection<int, array{source_type: class-string, source_key: mixed}>  $results
     * @return EloquentCollection<int, Model>
     */
    public function map(Builder $builder, $results, $model): EloquentCollection
    {
        if ($results->isEmpty()) {
            return $model->newCollection();
        }

        $keys = $results->pluck('source_key')->values()->all();

        if (! method_exists($model, 'getScoutModelsByIds')) {
            return $model->newCollection();
        }

        return $model->getScoutModelsByIds($builder, $keys)
            ->sortBy(function (Model $found) use ($keys): int {
                if (! method_exists($found, 'getScoutKey')) {
                    return PHP_INT_MAX;
                }

                $position = array_search($found->getScoutKey(), $keys, true);

                return $position === false ? PHP_INT_MAX : $position;
            })
            ->values();
    }

    public function paginate(Builder $builder, $perPage, $page): Collection
    {
        return $this->chunkHits($builder)
            ->forPage($page, $perPage)
            ->values();
    }

    public function mapIds($results): Collection
    {
        return collect($results)->pluck('source_key');
    }

    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        return $this->map($builder, $results, $model)->lazy();
    }

    public function getTotalCount($results): int
    {
        return count($results);
    }

    public function flush($model): void
    {
        $cleanup = app(VectorChunkCleanup::class);

        $model::query()->each(function (Model $record) use ($cleanup): void {
            if (! method_exists($record, 'vectorDocuments')) {
                return;
            }

            $record->vectorDocuments()->each(function (VectorDocument $document) use ($cleanup): void {
                $cleanup->purgeDocumentChunks($document);
                $document->forceDelete();
            });
        });
    }

    public function delete($models): void
    {
        $cleanup = app(VectorChunkCleanup::class);

        $models->each(function (Model $model) use ($cleanup): void {
            if (! method_exists($model, 'vectorDocuments')) {
                return;
            }

            $model->vectorDocuments()->each(function (VectorDocument $document) use ($cleanup): void {
                $cleanup->purgeDocumentChunks($document);
                $document->forceDelete();
            });
        });
    }

    public function createIndex($name, array $options = []): void {}

    public function deleteIndex($name): void {}
}
