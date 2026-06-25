<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Drivers;

use Eloquage\LaravelVectorSearch\Contracts\SupportsKeywordSearch;
use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
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
        if (empty($chunk->embedding)) {
            return;
        }

        $chunk->loadMissing('vectorDocument.vectorIndex');

        $document = $chunk->vectorDocument;
        $index = $document?->vectorIndex;

        if ($document === null || $index === null) {
            return;
        }

        $this->ensureIndex($index);

        $metadata = is_array($document->metadata) ? $document->metadata : [];

        $payload = [
            'id' => (string) $chunk->id,
            'content' => $chunk->content,
            'chunk_index' => $chunk->chunk_index,
            'title' => $document->title,
            'collection' => $metadata['collection'] ?? null,
            'case_number' => $metadata['case_number'] ?? $metadata['collection'] ?? null,
            'canonical_basename' => $metadata['canonical_basename'] ?? null,
            'source_file' => $metadata['source_file'] ?? null,
            '_vectors' => [
                'default' => [
                    'embeddings' => [$chunk->embedding],
                ],
            ],
        ];

        $this->index($index)->addDocuments([$payload]);
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
