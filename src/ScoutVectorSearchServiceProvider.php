<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch;

use Eloquage\LaravelVectorSearch\Engines\VectorSearchEngine;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class ScoutVectorSearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!class_exists(EngineManager::class)) {
            return;
        }

        resolve(EngineManager::class)->extend('vector-search', function (): VectorSearchEngine {
            return new VectorSearchEngine;
        });
    }
}
