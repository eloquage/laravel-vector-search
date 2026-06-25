<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Concerns;

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Eloquage\LaravelVectorSearch\Services\VectorDocumentPurger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
trait SearchableVector
{
    public static function bootSearchableVector(): void
    {
        static::saved(function (Model $model): void {
            if (method_exists($model, 'shouldSyncVectorDocument') && ! $model->shouldSyncVectorDocument()) {
                return;
            }

            $model->syncVectorDocument();
        });

        static::deleted(function (Model $model): void {
            $model->purgeVectorDocuments();
        });
    }

    public function shouldSyncVectorDocument(): bool
    {
        if ($this->wasRecentlyCreated) {
            return true;
        }

        return $this->wasChanged(['title', 'content', 'name', 'body']);
    }

    public function syncVectorDocument(): void
    {
        $indexName = $this->vectorSearchIndexName();
        $index = VectorIndex::query()
            ->where('name', $indexName)
            ->first();

        if ($index === null) {
            return;
        }

        $document = $this->vectorDocuments()
            ->where('vector_index_id', $index->id)
            ->first();

        $attributes = [
            'vector_index_id' => $index->id,
            'title' => $this->vectorSearchTitle(),
            'content' => $this->vectorSearchContent(),
            'metadata' => $this->vectorSearchMetadata(),
            'status' => DocumentStatus::DRAFT,
        ];

        if ($document === null) {
            $document = $this->vectorDocuments()->create($attributes);
        } else {
            $document->update($attributes);
        }

        ProcessVectorDocument::dispatch($document);
    }

    public function purgeVectorDocuments(): void
    {
        $purger = app(VectorDocumentPurger::class);

        $this->vectorDocuments()->each(function (VectorDocument $document) use ($purger): void {
            $purger->purge($document);
        });
    }

    public function vectorSearchIndexName(): string
    {
        return (string) (config('vector-search.scout.default_index') ?? config('vector-search.default_index',
            'default'));
    }

    /**
     * @return MorphMany<VectorDocument, $this>
     */
    public function vectorDocuments(): MorphMany
    {
        return $this->morphMany(VectorDocument::class, 'source');
    }

    public function vectorSearchTitle(): string
    {
        $key = $this->getKey();

        return (string) ($this->getAttribute('title') ?? $this->getAttribute('name') ?? "Record {$key}");
    }

    public function vectorSearchContent(): string
    {
        if (method_exists($this, 'toSearchableArray')) {
            return json_encode($this->toSearchableArray(), JSON_THROW_ON_ERROR);
        }

        return (string) ($this->getAttribute('content') ?? $this->getAttribute('body') ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function vectorSearchMetadata(): array
    {
        return [
            'model' => static::class,
            'id' => $this->getKey(),
        ];
    }
}
