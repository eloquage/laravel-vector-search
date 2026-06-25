<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Drivers;

use Eloquage\LaravelVectorSearch\Contracts\SupportsKeywordSearch;
use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Eloquage\LaravelVectorSearch\Support\ChunkMetadataMapper;
use Illuminate\Support\Collection;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Throwable;

class MeilisearchDriver implements SupportsKeywordSearch, VectorDriverInterface
{
    /**
     * @var array<string, bool>
     */
    protected array $ensuredIndexes = [];

    public function __construct(
        protected string $host,
        protected ?string $key = null,
        protected ?Client $client = null,
    ) {}

    public function upsert(VectorChunk $chunk): void
    {
        $this->upsertBatch(new Collection([$chunk]));
    }

    public function upsertBatch(Collection $chunks): void
    {
        $chunks = $chunks->filter(fn (VectorChunk $chunk): bool => ! empty($chunk->embedding));

        if ($chunks->isEmpty()) {
            return;
        }

        $chunks->each->loadMissing('vectorDocument.vectorIndex');

        $grouped = $chunks->groupBy(
            fn (VectorChunk $chunk): string => $chunk->vectorDocument->vectorIndex->name ?? 'default'
        );

        foreach ($grouped as $indexName => $indexChunks) {
            $firstChunk = $indexChunks->first();
            $index = $firstChunk?->vectorDocument?->vectorIndex;

            if ($index === null) {
                continue;
            }

            $this->ensureIndex($index);

            $payloads = $indexChunks
                ->map(function (VectorChunk $chunk): ?array {
                    $document = $chunk->vectorDocument;

                    if ($document === null) {
                        return null;
                    }

                    return ChunkMetadataMapper::toMeilisearchPayload($chunk, $document);
                })
                ->filter()
                ->values()
                ->all();

            if ($payloads !== []) {
                $this->index($index)->addDocuments($payloads);
            }
        }
    }

    public function search(VectorIndex $index, array $vector, int $limit = 5): Collection
    {
        $this->ensureIndex($index);

        $searchParams = [
            'limit' => $limit,
            'vector' => $vector,
            'hybrid' => [
                'semanticRatio' => 1,
                'embedder' => 'default',
            ],
        ];

        $response = $this->index($index)->search('', $searchParams);

        return $this->mapHitsToChunks($response->getHits());
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function keywordSearch(VectorIndex $index, string $query, int $limit, array $filters = []): Collection
    {
        $this->ensureIndex($index);

        $searchParams = [
            'limit' => $limit,
        ];

        $filter = $this->buildFilterString($filters);

        if ($filter !== '') {
            $searchParams['filter'] = $filter;
        }

        $response = $this->index($index)->search($query, $searchParams);

        return $this->mapHitsToChunks($response->getHits());
    }

    public function delete(VectorChunk $chunk): void
    {
        $chunk->loadMissing('vectorDocument.vectorIndex');

        $index = $chunk->vectorDocument?->vectorIndex;

        if ($index === null) {
            return;
        }

        $this->index($index)->deleteDocument((string) $chunk->id);
    }

    protected function ensureIndex(VectorIndex $index): void
    {
        $indexName = $index->name;

        if (isset($this->ensuredIndexes[$indexName])) {
            return;
        }

        $client = $this->client();

        try {
            $client->getIndex($indexName);
        } catch (Throwable) {
            $client->createIndex($indexName, ['primaryKey' => 'id']);
        }

        $this->index($index)->updateSettings([
            'filterableAttributes' => [
                'collection',
                'case_number',
                'canonical_basename',
                'chunk_index',
                'title',
                'source_file',
            ],
            'searchableAttributes' => [
                'content',
                'title',
                'canonical_basename',
            ],
        ]);

        $this->ensuredIndexes[$indexName] = true;
    }

    protected function index(VectorIndex $index): Indexes
    {
        return $this->client()->index($index->name);
    }

    protected function client(): Client
    {
        return $this->client ??= new Client($this->host, $this->key);
    }

    /**
     * @param  array<int, array<string, mixed>>  $hits
     * @return Collection<int, VectorChunk>
     */
    protected function mapHitsToChunks(array $hits): Collection
    {
        $ids = collect($hits)
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        if ($ids === []) {
            return new Collection;
        }

        return VectorChunk::query()
            ->whereIn('id', $ids)
            ->whereHas('vectorDocument', function ($query): void {
                $query->whereNull('deleted_at')
                    ->whereHas('vectorIndex', fn ($indexQuery) => $indexQuery->whereNull('deleted_at'));
            })
            ->get()
            ->sortBy(function (VectorChunk $chunk) use ($ids): int {
                $position = array_search($chunk->id, $ids, true);

                return $position === false ? PHP_INT_MAX : $position;
            })
            ->values();
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function buildFilterString(array $filters): string
    {
        $parts = [];

        foreach ($filters as $attribute => $value) {
            if ($value === '') {
                continue;
            }

            $escaped = str_replace('"', '\\"', $value);
            $parts[] = "{$attribute} = \"{$escaped}\"";
        }

        return implode(' AND ', $parts);
    }
}
