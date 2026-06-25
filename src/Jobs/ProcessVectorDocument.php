<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Jobs;

use Eloquage\LaravelVectorSearch\Contracts\TextSplitterInterface;
use Eloquage\LaravelVectorSearch\Enums\ChunkingStrategy;
use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Services\Chunking\RecursiveCharacterSplitter;
use Eloquage\LaravelVectorSearch\Services\Chunking\SentenceSplitter;
use Eloquage\LaravelVectorSearch\Services\Chunking\TokenLimitSplitter;
use Eloquage\LaravelVectorSearch\Services\VectorDocumentPurger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
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
        return 'vector-document:'.$this->document->id;
    }

    public function handle(VectorDocumentPurger $purger): void
    {
        try {
            $this->document->loadMissing('vectorIndex');
            $this->document->update(['status' => DocumentStatus::PROCESSING]);

            $index = $this->document->vectorIndex;

            if ($index === null) {
                throw new RuntimeException('Vector document is missing its vector index.');
            }

            $splitter = $this->resolveSplitter($index->chunking_strategy);

            $chunkTexts = $splitter->split(
                $this->document->content,
                $index->chunk_size,
                $index->chunk_overlap
            );

            DB::transaction(function () use ($purger, $chunkTexts): void {
                $purger->purgeChunks($this->document);

                if ($chunkTexts === []) {
                    return;
                }

                $now = now();
                $rows = [];

                foreach ($chunkTexts as $i => $text) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'vector_document_id' => $this->document->id,
                        'chunk_index' => $i,
                        'content' => $text,
                        'embedding' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                VectorChunk::query()->insert($rows);
            });

            $this->document->update(['status' => DocumentStatus::EMBEDDING]);

            EmbedVectorChunks::dispatch($this->document)->afterCommit();

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

    protected function resolveSplitter(ChunkingStrategy $strategy): TextSplitterInterface
    {
        $splitters = config('vector-search.splitters', []);

        if (isset($splitters[$strategy->value])) {
            $class = $splitters[$strategy->value];

            if (is_string($class) && is_subclass_of($class, TextSplitterInterface::class)) {
                return app($class);
            }
        }

        return match ($strategy) {
            ChunkingStrategy::RECURSIVE_CHARACTER => new RecursiveCharacterSplitter,
            ChunkingStrategy::TOKEN_LIMIT => new TokenLimitSplitter,
            ChunkingStrategy::SENTENCE => new SentenceSplitter,
        };
    }
}
