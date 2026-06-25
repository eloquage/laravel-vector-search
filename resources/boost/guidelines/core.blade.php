# Laravel Vector Search — Core Package

This is the **core** vector search package. Optional integrations ship separately:

- `eloquage/filament-vector-search` — Filament v5 admin plugin
- `eloquage/laravel-vector-search-ai` — Laravel AI SDK + MCP
- `eloquage/laravel-document-archive-vector` — Document archive search bridge

## Architecture

`VectorIndex` → `VectorDocument` → `VectorChunk` → embedding provider → vector driver.

Drivers: `postgres_vector`, `meilisearch`, `pinecone`.
Embedding providers: `openai`, `ollama`, `cohere`, `null` (testing only).

Use `LaravelVectorSearch::forIndex('name')->search($query)` for semantic search.
Use `LaravelVectorSearch::indexDocument($document)` to queue chunking + embedding.

## Jobs

`ProcessVectorDocument` and `EmbedVectorChunks` implement `ShouldBeUnique` per `vector_document_id`, with `$tries = 3`, `$backoff = [10, 60, 300]`, `$timeout = 300`.

- Chunk purge + insert runs in a DB transaction
- `EmbedVectorChunks` dispatches with `afterCommit()`
- `EmbedVectorChunks` calls `VectorDriverInterface::upsertBatch()` after batch embedding

Re-indexing purges remote vectors via `VectorChunkCleanup` before deleting chunk rows.

## Events

| Event | When |
|-------|------|
| `VectorDocumentIndexed` | `EmbedVectorChunks` completes successfully |
| `VectorDocumentFailed` | Processing or embedding fails |

Archive bridge packages should listen to these events rather than polling document status.

## Index naming

| Package | Config key | Default |
|---------|-----------|---------|
| vector-search | `default_index` | `default` |
| document-archive / document-archive-mcp | `vector_index` | `document-archive` |
| Scout | `scout.default_index` | — |

## Extension points

- Register custom drivers via `VectorDriverManager::extend()`
- Register custom embedding providers via `EmbeddingProviderManager::extend()`
- Register custom splitters in `config('vector-search.splitters')`
- `LaravelVectorSearch::configure(fn ($drivers, $embeddings) => ...)`
- `EmbeddingProviderManager::isConfigured(?string $driver)` for credential checks
- `VectorSearchConfigValidator` runs on boot (warnings by default; set `strict_boot_validation` to throw)
- Optional Scout engine: set `SCOUT_DRIVER=vector-search` and use `SearchableVector` trait

## Config

- `vector-search.embedding_batch_size` — batch size for OpenAI/Cohere embed APIs (default 100)
- `vector-search.embedding_timeout` — HTTP timeout for embedding providers (default 60)
- `vector-search.allow_null_embedding_provider` — allow null provider outside local/testing (default false)
- `vector-search.index_defaults.dimensions` — production embedding column size (default 1536)
- `vector-search.testing.embedding_dimensions` — null provider size in tests only
