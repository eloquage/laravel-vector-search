<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch;

use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LaravelVectorSearch
{
    /** @var array<int, callable(VectorDriverManager, EmbeddingProviderManager): void> */
    protected static array $configurators = [];

    public function __construct(
        protected EmbeddingProviderManager $embeddings,
        protected VectorDriverManager $drivers,
    ) {}

    /**
     * @param  callable(VectorDriverManager, EmbeddingProviderManager): void  $configurator
     */
    public static function configure(callable $configurator): void
    {
        static::$configurators[] = $configurator;
    }

    public static function runConfigurators(VectorDriverManager $drivers, EmbeddingProviderManager $embeddings): void
    {
        foreach (static::$configurators as $configurator) {
            $configurator($drivers, $embeddings);
        }
    }

    public function forIndex(string|VectorIndex $index): VectorSearchIndex
    {
        if ($index instanceof VectorIndex) {
            return new VectorSearchIndex($index, $this->embeddings, $this->drivers);
        }

        $resolved = VectorIndex::query()->where('name', $index)->first();

        if ($resolved === null) {
            throw (new ModelNotFoundException)->setModel(VectorIndex::class, [$index]);
        }

        return new VectorSearchIndex($resolved, $this->embeddings, $this->drivers);
    }

    public function indexDocument(VectorDocument $document): void
    {
        ProcessVectorDocument::dispatch($document);
    }

    public function embeddings(): EmbeddingProviderManager
    {
        return $this->embeddings;
    }

    public function drivers(): VectorDriverManager
    {
        return $this->drivers;
    }
}
