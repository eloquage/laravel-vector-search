<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Services\Chunking;

use Eloquage\LaravelVectorSearch\Contracts\TextSplitterInterface;

class TokenLimitSplitter implements TextSplitterInterface
{
    public function split(string $text, int $chunkSize, int $chunkOverlap): array
    {
        // Simple approximation: 1 token ~ 4 characters.
        // A real implementation would use a tokenizer like tiktoken.
        $approxCharLimit = $chunkSize * 4;
        $approxCharOverlap = $chunkOverlap * 4;

        $words = explode(' ', $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($words as $word) {
            if (strlen($currentChunk) + strlen($word) + 1 > $approxCharLimit) {
                $chunks[] = trim($currentChunk);
                // To do proper overlap, we'd slice words from the end of currentChunk.
                // Doing a very basic reset here.
                $currentChunk = $word.' ';
            } else {
                $currentChunk .= $word.' ';
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }
}
