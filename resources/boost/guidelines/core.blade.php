# Laravel Vector Search — Core Package

This is the **core** vector search package. Optional integrations ship separately:

- `eloquage/filament-vector-search` — Filament v5 admin plugin
- `eloquage/laravel-vector-search-ai` — Laravel AI SDK + MCP

## Architecture

`VectorIndex` → `VectorDocument` → `VectorChunk` → embedding driver → vector driver.

Use `VectorSearch::forIndex('name')->search($query)` for semantic search.
Use `VectorSearch::indexDocument($document)` to queue chunking + embedding.

## Extension points

Register custom drivers and embedding providers in `AppServiceProvider`:

```php
use Eloquage\LaravelVectorSearch\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;

LaravelVectorSearch::configure(function (VectorDriverManager $drivers, EmbeddingProviderManager $embeddings): void {
    $drivers->extend('custom', fn () => new CustomVectorDriver);
    $embeddings->extend('custom', fn () => new CustomEmbeddingProvider);
});
```

Custom text splitters can be registered in `config/vector-search.php` under `splitters`.

Optional Scout engine: set `SCOUT_DRIVER=vector-search` and use `SearchableVector` trait.
