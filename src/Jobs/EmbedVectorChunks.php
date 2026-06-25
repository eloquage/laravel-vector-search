<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Jobs;

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class EmbedVectorChunks implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 300;

    public function __construct(
        public VectorDocument $document
    ) {}

    public function uniqueId(): string
    {
        return 'vector-document-embed:'.$this->document->id;
    }

    public function handle(
        EmbeddingProviderManager $embeddings,
        VectorDriverManager $drivers,
    ): void {
        try {
            $this->document->loadMissing('vectorIndex');

            $index = $this->document->vectorIndex;

            if ($index === null) {
                throw new RuntimeException('Vector document is missing its vector index.');
            }

            $embedder = $embeddings->forIndex($index);
            $driver = $drivers->driver($index->driver->value);

            $chunks = $this->document->chunks()->orderBy('chunk_index')->get();

            if ($chunks->isEmpty()) {
                $this->document->update(['status' => DocumentStatus::COMPLETED]);

                return;
            }

            $texts = $chunks->pluck('content')->toArray();
            $vectors = $embedder->embedBatch($texts);

            if (count($vectors) !== $chunks->count()) {
                throw new RuntimeException(sprintf(
                    'Embedding provider returned %d vectors for %d chunks.',
                    count($vectors),
                    $chunks->count(),
                ));
            }

            foreach ($vectors as $vector) {
                if ($index->dimensions !== null && count($vector) !== $index->dimensions) {
                    throw new InvalidArgumentException(sprintf(
                        'Embedding dimension mismatch: expected %d, got %d.',
                        $index->dimensions,
                        count($vector),
                    ));
                }
            }

            DB::transaction(function () use ($chunks, $vectors, $driver): void {
                $upsertRows = [];

                foreach ($chunks as $i => $chunk) {
                    if (! $chunk instanceof VectorChunk) {
                        continue;
                    }

                    $embedding = $vectors[$i];

                    $upsertRows[] = [
                        'id' => $chunk->id,
                        'vector_document_id' => $chunk->vector_document_id,
                        'chunk_index' => $chunk->chunk_index,
                        'content' => $chunk->content,
                        'embedding' => json_encode($embedding),
                        'created_at' => $chunk->created_at,
                        'updated_at' => now(),
                    ];

                    $chunk->setAttribute('embedding', $embedding);
                }

                $this->document->chunks()->upsert(
                    $upsertRows,
                    ['id'],
                    ['embedding', 'updated_at'],
                );

                $driver->upsertMany($chunks);
            });

            $this->document->update(['status' => DocumentStatus::COMPLETED]);

        } catch (Throwable $e) {
            $this->markFailed($e);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception);
    }

    protected function markFailed(Throwable $exception): void
    {
        $this->document->update([
            'status' => DocumentStatus::FAILED,
            'error_log' => $exception->getMessage()."\n".$exception->getTraceAsString(),
        ]);
    }
}
