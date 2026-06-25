<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Jobs;

use Eloquage\LaravelVectorSearch\Contracts\TextSplitterInterface;
use Eloquage\LaravelVectorSearch\Enums\ChunkingStrategy;
use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Services\Chunking\RecursiveCharacterSplitter;
use Eloquage\LaravelVectorSearch\Services\Chunking\SentenceSplitter;
use Eloquage\LaravelVectorSearch\Services\Chunking\TokenLimitSplitter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessVectorDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public VectorDocument $document
    ) {}

    public function handle(): void
    {
        try {
            $this->document->update(['status' => DocumentStatus::PROCESSING]);

            $index = $this->document->vectorIndex;
            $splitter = $this->resolveSplitter($index->chunking_strategy);

            // Clear old chunks
            $this->document->chunks()->delete();

            // Split content
            $chunkTexts = $splitter->split(
                $this->document->content,
                $index->chunk_size,
                $index->chunk_overlap
            );

            // Save chunk records without embeddings
            foreach ($chunkTexts as $i => $text) {
                $this->document->chunks()->create([
                    'chunk_index' => $i,
                    'content' => $text,
                    'embedding' => null,
                ]);
            }

            // Update status and dispatch embedding job
            $this->document->update(['status' => DocumentStatus::EMBEDDING]);

            EmbedVectorChunks::dispatch($this->document);

        } catch (Throwable $e) {
            $this->document->update([
                'status' => DocumentStatus::FAILED,
                'error_log' => $e->getMessage()."\n".$e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function resolveSplitter(ChunkingStrategy $strategy): TextSplitterInterface
    {
        return match ($strategy) {
            ChunkingStrategy::RECURSIVE_CHARACTER => new RecursiveCharacterSplitter,
            ChunkingStrategy::TOKEN_LIMIT => new TokenLimitSplitter,
            ChunkingStrategy::SENTENCE => new SentenceSplitter,
        };
    }
}
