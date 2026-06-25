<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Jobs;

use Eloquage\LaravelVectorSearch\Contracts\TextSplitterInterface;
use Eloquage\LaravelVectorSearch\Enums\ChunkingStrategy;
use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Events\VectorDocumentFailed;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Services\Chunking\RecursiveCharacterSplitter;
use Eloquage\LaravelVectorSearch\Services\Chunking\SentenceSplitter;
use Eloquage\LaravelVectorSearch\Services\Chunking\TokenLimitSplitter;
use Eloquage\LaravelVectorSearch\Services\VectorChunkCleanup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ProcessVectorDocument implements ShouldBeUnique, ShouldQueue
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

    public function handle(VectorChunkCleanup $cleanup): void
    {
        try {
            $this->document->update(['status' => DocumentStatus::PROCESSING]);

            $index = $this->document->vectorIndex;
            $splitter = $this->resolveSplitter($index->chunking_strategy);

            DB::transaction(function () use ($cleanup, $splitter, $index): void {
                $cleanup->purgeDocumentChunks($this->document);

                $chunkTexts = $splitter->split(
                    $this->document->content,
                    $index->chunk_size,
                    $index->chunk_overlap
                );

                foreach ($chunkTexts as $i => $text) {
                    $this->document->chunks()->create([
                        'chunk_index' => $i,
                        'content' => $text,
                        'embedding' => null,
                    ]);
                }

                $this->document->update(['status' => DocumentStatus::EMBEDDING]);
            });

            EmbedVectorChunks::dispatch($this->document)->afterCommit();

        } catch (Throwable $e) {
            $this->document->update([
                'status' => DocumentStatus::FAILED,
                'error_log' => $e->getMessage()."\n".$e->getTraceAsString(),
            ]);

            event(new VectorDocumentFailed($this->document, $e->getMessage()));

            throw $e;
        }
    }

    protected function resolveSplitter(ChunkingStrategy $strategy): TextSplitterInterface
    {
        $customSplitters = config('vector-search.splitters', []);
        $strategyValue = $strategy->value;

        if (is_array($customSplitters) && isset($customSplitters[$strategyValue])) {
            $class = $customSplitters[$strategyValue];

            if (is_string($class) && class_exists($class)) {
                $splitter = app($class);

                if ($splitter instanceof TextSplitterInterface) {
                    return $splitter;
                }
            }

            throw new InvalidArgumentException("Splitter [{$class}] must implement TextSplitterInterface.");
        }

        return match ($strategy) {
            ChunkingStrategy::RECURSIVE_CHARACTER => new RecursiveCharacterSplitter,
            ChunkingStrategy::TOKEN_LIMIT => new TokenLimitSplitter,
            ChunkingStrategy::SENTENCE => new SentenceSplitter,
        };
    }
}
