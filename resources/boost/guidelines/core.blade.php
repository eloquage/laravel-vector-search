# Laravel Vector Search — Core Package

This is the **core** vector search package. Optional integrations ship separately:

- `eloquage/filament-vector-search` — Filament v5 admin plugin
- `eloquage/laravel-vector-search-ai` — Laravel AI SDK + MCP

## Architecture

`VectorIndex` → `VectorDocument` → `VectorChunk` → embedding driver → vector driver.

Use `VectorSearch::forIndex('name')->search($query)` for semantic search.
Use `VectorSearch::indexDocument($document)` to queue chunking + embedding.

## Extension points

- Register custom drivers via `VectorDriverManager::extend()`
- Register custom embedding providers via `EmbeddingProviderManager::extend()`
- Optional Scout engine: set `SCOUT_DRIVER=vector-search` and use `SearchableVector` trait
