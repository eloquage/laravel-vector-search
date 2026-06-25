<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch;

use Eloquage\LaravelVectorSearch\Contracts\EmbeddingProviderInterface;
use Eloquage\LaravelVectorSearch\Contracts\VectorDriverInterface;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Observers\VectorDocumentObserver;
use Eloquage\LaravelVectorSearch\Services\VectorChunkCleanup;
use Laravel\Scout\EngineManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelVectorSearchServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-vector-search')
            ->hasConfigFile()
            ->hasMigrations([
                'create_vector_indexes_table',
                'create_vector_documents_table',
                'create_vector_chunks_table',
                'alter_vector_chunks_embedding_to_vector_column',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(VectorDriverManager::class);
        $this->app->singleton(EmbeddingProviderManager::class);
        $this->app->singleton(VectorChunkCleanup::class);
        $this->app->singleton(LaravelVectorSearch::class);

        $this->app->bind(VectorDriverInterface::class,
            fn ($app): VectorDriverInterface => $app->make(VectorDriverManager::class)->driver());
        $this->app->bind(EmbeddingProviderInterface::class,
            fn ($app): EmbeddingProviderInterface => $app->make(EmbeddingProviderManager::class)->driver());
    }

    public function packageBooted(): void
    {
        LaravelVectorSearch::runConfigurators(
            $this->app->make(VectorDriverManager::class),
            $this->app->make(EmbeddingProviderManager::class),
        );

        VectorDocument::observe(VectorDocumentObserver::class);

        if (class_exists(EngineManager::class)) {
            $this->app->register(ScoutVectorSearchServiceProvider::class);
        }
    }
}
