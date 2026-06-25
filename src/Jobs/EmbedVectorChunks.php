<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Jobs;

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Events\VectorDocumentFailed;
use Eloquage\LaravelVectorSearch\Events\VectorDocumentIndexed;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        return $this->document->id;
    }

    public function handle(
        EmbeddingProviderManager $embeddingManager,
        VectorDriverManager $drivers,
    ): void {
        try {
            $index = $this->document->vectorIndex;
            $embedder = $embeddingManager->driver($index->embedding_provider->value);
            $driver = $drivers->driver($index->driver->value);

            $chunks = $this->document->chunks()->orderBy('chunk_index')->get();
            if ($chunks->isEmpty()) {
                $this->document->update(['status' => DocumentStatus::COMPLETED]);
                event(new VectorDocumentIndexed($this->document));

                return;
            }

            $texts = $chunks->pluck('content')->toArray();

            $vectors = $embedder->embedBatch($texts);

            foreach ($chunks as $i => $chunk) {
                $chunk->update(['embedding' => $vectors[$i] ?? null]);

                $driver->upsert($chunk);
            }

            $this->document->update(['status' => DocumentStatus::COMPLETED]);

            event(new VectorDocumentIndexed($this->document));

        } catch (Throwable $e) {
            $this->document->update([
                'status' => DocumentStatus::FAILED,
                'error_log' => $e->getMessage()."\n".$e->getTraceAsString(),
            ]);

            event(new VectorDocumentFailed($this->document, $e->getMessage()));

            throw $e;
        }
    }
}
