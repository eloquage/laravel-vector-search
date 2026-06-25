<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Contracts;

interface TextSplitterInterface
{
    /**
     * Split text into smaller chunks based on the strategy.
     *
     * @return array<string>
     */
    public function split(string $text, int $chunkSize, int $chunkOverlap): array;
}
