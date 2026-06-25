<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Services\Chunking;

use Eloquage\LaravelVectorSearch\Contracts\TextSplitterInterface;

class SentenceSplitter implements TextSplitterInterface
{
    public function split(string $text, int $chunkSize, int $chunkOverlap): array
    {
        // Basic sentence splitting by punctuation
        $sentences = preg_split('/(?<=[.?!])\s+(?=[a-zA-Z])/', $text);

        $chunks = [];
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            // If adding this sentence exceeds chunk size (in characters for this naive implementation)
            if (strlen($currentChunk) + strlen($sentence) > $chunkSize) {
                if (trim($currentChunk) !== '') {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $sentence.' ';
            } else {
                $currentChunk .= $sentence.' ';
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }
}
