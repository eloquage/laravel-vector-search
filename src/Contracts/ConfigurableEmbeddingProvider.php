<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Contracts;

interface ConfigurableEmbeddingProvider extends EmbeddingProviderInterface
{
    public function withModel(string $model): static;

    public function withDimensions(?int $dimensions): static;
}
