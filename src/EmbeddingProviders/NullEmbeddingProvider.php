<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\EmbeddingProviders;

use Eloquage\LaravelVectorSearch\Contracts\EmbeddingProviderInterface;

class NullEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * @return array<float>
     */
    public function embed(string $text): array
    {
        return $this->deterministicVector($text);
    }

    /**
     * @return array<float>
     */
    public function embedQuery(string $text): array
    {
        return $this->embed($text);
    }

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    public function dimensions(): ?int
    {
        return (int) config('vector-search.testing.embedding_dimensions', 8);
    }

    /**
     * @return array<float>
     */
    protected function deterministicVector(string $text): array
    {
        $dimensions = $this->dimensions() ?? 8;
        $vector = [];

        for ($i = 0; $i < $dimensions; $i++) {
            $vector[] = (float) (((crc32($text.':'.$i) % 1000) / 1000) - 0.5);
        }

        return $vector;
    }
}
