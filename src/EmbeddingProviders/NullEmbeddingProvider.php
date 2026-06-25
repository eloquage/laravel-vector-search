<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\EmbeddingProviders;

use Eloquage\LaravelVectorSearch\Contracts\ConfigurableEmbeddingProvider;

class NullEmbeddingProvider implements ConfigurableEmbeddingProvider
{
    public function __construct(
        protected ?int $dimensions = null,
    ) {}

    public function withModel(string $model): static
    {
        return clone $this;
    }

    public function withDimensions(?int $dimensions): static
    {
        $clone = clone $this;
        $clone->dimensions = $dimensions;

        return $clone;
    }

    /**
     * @return array<float>
     */
    public function embed(string $text): array
    {
        return $this->fakeVector($text);
    }

    /**
     * @return array<float>
     */
    protected function fakeVector(string $text): array
    {
        $dimensions = $this->dimensions ?? (int) config('vector-search.testing.embedding_dimensions', 8);
        $seed = crc32($text);
        $vector = [];

        for ($i = 0; $i < $dimensions; $i++) {
            $vector[] = (($seed >> ($i % 32)) & 0xFF) / 255;
        }

        return $vector;
    }

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->fakeVector($text), $texts);
    }
}
