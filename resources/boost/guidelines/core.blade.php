# Laravel Vector Search — Core Package

This is the **core** vector search package. Optional integrations ship separately:

- `eloquage/filament-vector-search` — Filament v5 admin plugin
- `eloquage/laravel-vector-search-ai` — Laravel AI SDK + MCP
- `eloquage/laravel-document-archive-vector` — Document archive search bridge

## Architecture

`VectorIndex` → `VectorDocument` → `VectorChunk` → embedding driver → vector driver.

Use `LaravelVectorSearch::forIndex('name')->search($query)` for semantic search.
Use `LaravelVectorSearch::indexDocument($document)` to queue chunking + embedding.

## Jobs

`ProcessVectorDocument` and `EmbedVectorChunks` implement `ShouldBeUnique` per `vector_document_id`, with `$tries = 3`, `$backoff = [10, 60, 300]`, `$timeout = 300`.

Re-indexing purges remote vectors via `VectorChunkCleanup` before deleting chunk rows.

## Events

| Event | When |
|-------|------|
| `VectorDocumentIndexed` | `EmbedVectorChunks` completes successfully |
| `VectorDocumentFailed` | Processing or embedding fails |

## Extension points

- Register custom drivers via `VectorDriverManager::extend()`
- Register custom embedding providers via `EmbeddingProviderManager::extend()`
- `LaravelVectorSearch::configure(fn ($drivers, $embeddings) => ...)`
- `EmbeddingProviderManager::isConfigured(?string $driver)` for credential checks
- Optional Scout engine: set `SCOUT_DRIVER=vector-search` and use `SearchableVector` trait

## Config

- `vector-search.index_defaults.dimensions` — production embedding column size (default 1536)
- `vector-search.testing.embedding_dimensions` — null provider size in tests only
