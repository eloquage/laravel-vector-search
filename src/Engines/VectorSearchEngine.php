<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Engines;

use Eloquage\LaravelVectorSearch\Facades\LaravelVectorSearch;
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

    /**
     * @return Collection<int, array{source_type: class-string, source_key: mixed}>
     */
    protected function chunkHits(Builder $builder): Collection
    {
        $indexName = $builder->index ?? config('vector-search.scout.default_index',
            config('vector-search.default_index', 'default'));

        $chunks = LaravelVectorSearch::forIndex((string) $indexName)->search(
            $builder->query,
            $builder->limit ?? 10,
        );

        return $chunks
            ->load('vectorDocument.source')
            ->map(function ($chunk): ?array {
                $source = $chunk->vectorDocument?->source;

                if ($source === null) {
                    return null;
                }

                return [
                    'source_type' => $source::class,
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

        return $model->getScoutModelsByIds($builder, $keys)
            ->sortBy(fn (Model $found): int|false => array_search($found->getScoutKey(), $keys, true))
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
        $model::query()->each(function (Model $record): void {
            if (method_exists($record, 'vectorDocuments')) {
                $record->vectorDocuments()->delete();
            }
        });
    }

    public function delete($models): void
    {
        $models->each(function (Model $model): void {
            if (method_exists($model, 'vectorDocuments')) {
                $model->vectorDocuments()->delete();
            }
        });
    }

    public function createIndex($name, array $options = []): void {}

    public function deleteIndex($name): void {}
}
