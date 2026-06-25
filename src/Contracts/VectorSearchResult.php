<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Contracts;

use Eloquage\LaravelVectorSearch\Models\VectorChunk;

readonly class VectorSearchResult
{
    public function __construct(
        public VectorChunk $chunk,
        public ?float $score = null,
    ) {}

    /**
     * @return array{chunk_id: string, content: string, score: ?float}
     */
    public function toArray(): array
    {
        return [
            'chunk_id' => $this->chunk->id,
            'content' => $this->chunk->content,
            'score' => $this->score,
        ];
    }
}
