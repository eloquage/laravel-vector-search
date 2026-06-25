<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Drivers;

use Eloquage\LaravelVectorSearch\Contracts\SupportsKeywordSearch;
use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Drivers\Concerns\ProvidesDefaultVectorDriverOperations;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Collection;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Throwable;

class MeilisearchDriver implements SupportsKeywordSearch, VectorDriverInterface
{
    use ProvidesDefaultVectorDriverOperations;

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
        $this->upsertMany([$chunk]);
    }

    /**
     * @param  iterable<int, VectorChunk>  $chunks
     */
    public function upsertMany(iterable $chunks): void
    {
        $chunks = collect($chunks)->filter(fn (VectorChunk $chunk): bool => ! empty($chunk->embedding));

        if ($chunks->isEmpty()) {
            return;
        }

        $chunks->each->loadMissing('vectorDocument.vectorIndex');

        $grouped = $chunks->groupBy(fn (VectorChunk $chunk): string => (string) $chunk->vectorDocument?->vectorIndex?->id);

        foreach ($grouped as $indexChunks) {
            /** @var VectorChunk $first */
            $first = $indexChunks->first();
            $index = $first->vectorDocument?->vectorIndex;

            if ($index === null) {
                continue;
            }

            $this->ensureIndex($index);

            $payloads = $indexChunks
                ->map(fn (VectorChunk $chunk): array => $this->buildPayload($chunk))
                ->values()
                ->all();

            $batchSize = (int) config('vector-search.drivers.meilisearch.batch_size', 100);

            foreach (array_chunk($payloads, max(1, $batchSize)) as $batch) {
                $this->index($index)->addDocuments($batch);
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

    public function deleteByDocument(VectorDocument $document): void
    {
        $document->loadMissing('vectorIndex');

        $index = $document->vectorIndex;

        if ($index === null) {
            return;
        }

        $this->ensureIndex($index);

        $escapedId = str_replace('"', '\\"', (string) $document->id);

        $this->index($index)->deleteDocuments([
            'filter' => 'vector_document_id = "'.$escapedId.'"',
        ]);
    }

    protected function buildPayload(VectorChunk $chunk): array
    {
        $document = $chunk->vectorDocument;
        $metadata = is_array($document?->metadata) ? $document->metadata : [];

        $payload = [
            'id' => (string) $chunk->id,
            'content' => $chunk->content,
            'chunk_index' => $chunk->chunk_index,
            'title' => $document?->title,
            'vector_document_id' => (string) $document?->id,
            '_vectors' => [
                'default' => [
                    'embeddings' => [$chunk->embedding],
                ],
            ],
        ];

        foreach ($metadata as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $payload[(string) $key] = $value;
            }
        }

        return $payload;
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
            'filterableAttributes' => config('vector-search.drivers.meilisearch.filterable_attributes', [
                'vector_document_id',
                'chunk_index',
                'title',
                'collection',
                'case_number',
                'canonical_basename',
                'source_file',
            ]),
            'searchableAttributes' => config('vector-search.drivers.meilisearch.searchable_attributes', [
                'content',
                'title',
                'canonical_basename',
            ]),
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
            ->get()
            ->sortBy(fn (VectorChunk $chunk): int => array_search($chunk->id, $ids, true) ?: PHP_INT_MAX)
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
