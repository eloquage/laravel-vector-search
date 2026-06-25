<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Jobs;

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class EmbedVectorChunks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public VectorDocument $document
    ) {}

    public function handle(
        EmbeddingProviderManager $embeddings,
        VectorDriverManager $drivers,
    ): void {
        try {
            $index = $this->document->vectorIndex;
            $embedder = $embeddings->driver($index->embedding_provider->value);
            $driver = $drivers->driver($index->driver->value);

            $chunks = $this->document->chunks()->orderBy('chunk_index')->get();
            if ($chunks->isEmpty()) {
                $this->document->update(['status' => DocumentStatus::COMPLETED]);

                return;
            }

            // In a real scenario, respect rate limits and chunk the API calls themselves
            // Here we assume embedder handles batching correctly or we batch it
            $texts = $chunks->pluck('content')->toArray();

            $embeddings = $embedder->embedBatch($texts);

            foreach ($chunks as $i => $chunk) {
                // Assign embedding to chunk
                $chunk->update(['embedding' => $embeddings[$i] ?? null]);

                // Save to vector database (pgvector, meilisearch, etc.)
                $driver->upsert($chunk);
            }

            $this->document->update(['status' => DocumentStatus::COMPLETED]);

        } catch (Throwable $e) {
            $this->document->update([
                'status' => DocumentStatus::FAILED,
                'error_log' => $e->getMessage()."\n".$e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
