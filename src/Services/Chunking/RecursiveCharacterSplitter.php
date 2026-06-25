<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Services\Chunking;

use Eloquage\LaravelVectorSearch\Contracts\TextSplitterInterface;

class RecursiveCharacterSplitter implements TextSplitterInterface
{
    /**
     * Standard separators used in recursive character splitting.
     */
    protected array $separators = ["\n\n", "\n", ' ', ''];

    public function split(string $text, int $chunkSize, int $chunkOverlap): array
    {
        return $this->splitText($text, $chunkSize, $chunkOverlap, $this->separators);
    }

    protected function splitText(string $text, int $chunkSize, int $chunkOverlap, array $separators): array
    {
        $finalChunks = [];
        $separator = $separators[count($separators) - 1];

        foreach ($separators as $s) {
            if ($s === '') {
                $separator = $s;
                break;
            }
            if (str_contains($text, $s)) {
                $separator = $s;
                break;
            }
        }

        $splits = $separator !== '' ? explode($separator, $text) : str_split($text);

        $goodSplits = [];
        foreach ($splits as $split) {
            if (strlen($split) < $chunkSize) {
                $goodSplits[] = $split;
            } else {
                if (! empty($goodSplits)) {
                    $mergedText = $this->mergeSplits($goodSplits, $separator, $chunkSize, $chunkOverlap);
                    $finalChunks = array_merge($finalChunks, $mergedText);
                    $goodSplits = [];
                }
                if (! empty($separators)) {
                    $nextSeparators = array_slice($separators, 1);
                    if (! empty($nextSeparators)) {
                        $otherInfo = $this->splitText($split, $chunkSize, $chunkOverlap, $nextSeparators);
                        $finalChunks = array_merge($finalChunks, $otherInfo);

                        continue;
                    }
                }
                $finalChunks[] = $split;
            }
        }

        if (! empty($goodSplits)) {
            $mergedText = $this->mergeSplits($goodSplits, $separator, $chunkSize, $chunkOverlap);
            $finalChunks = array_merge($finalChunks, $mergedText);
        }

        return $finalChunks;
    }

    protected function mergeSplits(array $splits, string $separator, int $chunkSize, int $chunkOverlap): array
    {
        $docs = [];
        $currentDoc = [];
        $total = 0;

        foreach ($splits as $split) {
            $len = strlen($split) + ($total > 0 ? strlen($separator) : 0);
            if ($total + $len >= $chunkSize && $total > 0) {
                $docs[] = implode($separator, $currentDoc);
                // Handle overlap logic if needed, simplify for now by resetting
                while ($total > $chunkOverlap || ($total + $len > $chunkSize && $total > 0)) {
                    $total -= strlen($currentDoc[0]) + (count($currentDoc) > 1 ? strlen($separator) : 0);
                    array_shift($currentDoc);
                }
            }
            $currentDoc[] = $split;
            $total += $len;
        }

        if (! empty($currentDoc)) {
            $docs[] = implode($separator, $currentDoc);
        }

        return $docs;
    }
}
