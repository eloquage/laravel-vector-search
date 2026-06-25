<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Services\Chunking;

use Eloquage\LaravelVectorSearch\Contracts\TextSplitterInterface;

class TokenLimitSplitter implements TextSplitterInterface
{
    public function split(string $text, int $chunkSize, int $chunkOverlap): array
    {
        $approxCharLimit = $chunkSize * 4;
        $approxCharOverlap = $chunkOverlap * 4;

        $words = explode(' ', $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($words as $word) {
            if (strlen($currentChunk) + strlen($word) + 1 > $approxCharLimit) {
                $trimmed = trim($currentChunk);

                if ($trimmed !== '') {
                    $chunks[] = $trimmed;
                }

                $overlapText = $this->tailOverlap($trimmed, $approxCharOverlap);
                $currentChunk = $overlapText !== '' ? $overlapText.' '.$word.' ' : $word.' ';
            } else {
                $currentChunk .= $word.' ';
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    protected function tailOverlap(string $text, int $overlapChars): string
    {
        if ($overlapChars <= 0 || $text === '') {
            return '';
        }

        if (strlen($text) <= $overlapChars) {
            return $text;
        }

        $tail = substr($text, -$overlapChars);
        $spacePosition = strpos($tail, ' ');

        if ($spacePosition === false) {
            return $tail;
        }

        return trim(substr($tail, $spacePosition));
    }
}
