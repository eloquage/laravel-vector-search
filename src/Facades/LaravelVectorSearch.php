<?php

namespace Eloquage\LaravelVectorSearch\Facades;

use Eloquage\LaravelVectorSearch\LaravelVectorSearch as VectorSearchService;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\VectorSearchIndex;
use Illuminate\Support\Facades\Facade;

/**
 * @method static VectorSearchIndex forIndex(string|\Eloquage\LaravelVectorSearch\Models\VectorIndex $index)
 * @method static void indexDocument(VectorDocument $document)
 * @method static EmbeddingProviderManager embeddings()
 * @method static VectorDriverManager drivers()
 *
 * @see VectorSearchService
 */
class LaravelVectorSearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VectorSearchService::class;
    }
}
