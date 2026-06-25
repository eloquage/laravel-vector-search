<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * Generate an embedding for a single text (document/chunk context).
     *
     * @return array<float>
     */
    public function embed(string $text): array;

    /**
     * Generate an embedding optimized for search queries.
     *
     * @return array<float>
     */
    public function embedQuery(string $text): array;

    /**
     * Generate embeddings for multiple texts.
     *
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array;

    public function dimensions(): ?int;
}
