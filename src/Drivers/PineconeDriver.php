<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Drivers;

use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Eloquage\LaravelVectorSearch\Support\ChunkMetadataMapper;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PineconeDriver implements VectorDriverInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $indexHost,
        protected int $timeout = 60,
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

        foreach ($grouped as $namespace => $namespaceChunks) {
            $vectors = [];

            foreach ($namespaceChunks as $chunk) {
                $document = $chunk->vectorDocument;

                if ($document === null) {
                    continue;
                }

                $metadata = ChunkMetadataMapper::documentMetadata($document);

                $vectors[] = [
                    'id' => (string) $chunk->id,
                    'values' => $chunk->embedding,
                    'metadata' => array_filter([
                        'content' => $chunk->content,
                        'chunk_index' => $chunk->chunk_index,
                        'title' => $document->title,
                        'collection' => $metadata['collection'] ?? null,
                        'case_number' => $metadata['case_number'] ?? $metadata['collection'] ?? null,
                        'canonical_basename' => $metadata['canonical_basename'] ?? null,
                        'source_file' => $metadata['source_file'] ?? null,
                    ], fn (mixed $value): bool => $value !== null),
                ];
            }

            if ($vectors === []) {
                continue;
            }

            $this->request('POST', '/vectors/upsert', [
                'vectors' => $vectors,
                'namespace' => (string) $namespace,
            ]);
        }
    }

    public function search(VectorIndex $index, array $vector, int $limit = 5): Collection
    {
        $response = $this->request('POST', '/query', [
            'vector' => $vector,
            'topK' => $limit,
            'namespace' => $index->name,
            'includeMetadata' => true,
        ]);

        /** @var array<int, array{id?: string}> $matches */
        $matches = $response->json('matches', []);

        $ids = collect($matches)
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
            ->whereHas('vectorDocument', function ($query) use ($index): void {
                $query->where('vector_index_id', $index->id)
                    ->whereNull('deleted_at');
            })
            ->get()
            ->sortBy(function (VectorChunk $chunk) use ($ids): int {
                $position = array_search($chunk->id, $ids, true);

                return $position === false ? PHP_INT_MAX : $position;
            })
            ->values();
    }

    public function delete(VectorChunk $chunk): void
    {
        $chunk->loadMissing('vectorDocument.vectorIndex');

        $index = $chunk->vectorDocument?->vectorIndex;

        if ($index === null) {
            return;
        }

        $this->request('POST', '/vectors/delete', [
            'ids' => [(string) $chunk->id],
            'namespace' => $index->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function request(string $method, string $path, array $payload): Response
    {
        if ($this->apiKey === '' || $this->indexHost === '') {
            throw new RuntimeException('Pinecone API key and index host must be configured.');
        }

        try {
            return Http::withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->acceptJson()
                ->timeout($this->timeout)
                ->send($method, rtrim($this->indexHost, '/').$path, [
                    'json' => $payload,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Pinecone request failed: '.$exception->getMessage(),
                previous: $exception
            );
        }
    }
}
