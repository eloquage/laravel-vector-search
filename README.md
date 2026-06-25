# Laravel Vector Search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eloquage/laravel-vector-search.svg?style=flat-square)](https://packagist.org/packages/eloquage/laravel-vector-search)
[![GitHub Tests Action Status](https://github.com/eloquage/laravel-vector-search/actions/workflows/run-tests.yml/badge.svg)](https://github.com/eloquage/laravel-vector-search/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/eloquage/laravel-vector-search/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/eloquage/laravel-vector-search/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/eloquage/laravel-vector-search.svg?style=flat-square)](https://packagist.org/packages/eloquage/laravel-vector-search)

Semantic vector search for Laravel: chunk documents, generate embeddings, store vectors, and query by meaning. Ships with PostgreSQL/pgvector and Meilisearch drivers, pluggable embedding providers, queued indexing, and optional Laravel Scout integration.

Optional companion packages:

| Package                                                                                     | Purpose                                                      |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| [`eloquage/filament-vector-search`](https://github.com/eloquage/filament-vector-search)     | Filament v5 admin UI — indexes, documents, search playground |
| [`eloquage/laravel-vector-search-ai`](https://github.com/eloquage/laravel-vector-search-ai) | Laravel AI SDK tools, agents, and MCP server                 |

## Architecture

```
VectorIndex → VectorDocument → VectorChunk → embedding provider → vector driver → search()
```

**Indexing pipeline** (queued by default):

1. `ProcessVectorDocument` — split content into chunks using the index's `chunking_strategy`, `chunk_size`, and `chunk_overlap`
2. `EmbedVectorChunks` — batch-embed chunk text and persist vectors via the index driver (dispatched `afterCommit`)

**Search** embeds the query, then delegates similarity search to the index's vector driver. When the driver is Meilisearch, `keywordSearch()` provides full-text search with optional metadata filters.

## Requirements

| Requirement           | Notes                                                                                                      |
| --------------------- | ---------------------------------------------------------------------------------------------------------- |
| PHP                   | 8.4+                                                                                                       |
| Laravel               | 11, 12, or 13                                                                                              |
| PostgreSQL + pgvector | Default driver (`postgres_vector`); migrations call `Schema::ensureVectorExtensionExists()` on Laravel 13+ |
| Queue worker          | Required for production indexing via `indexDocument()` or `SearchableVector`                               |
| Meilisearch           | Optional; required for `meilisearch` driver and `keywordSearch()`                                          |
| Laravel Scout         | Optional; required for `SearchableVector` trait                                                            |

## Installation

```bash
composer require eloquage/laravel-vector-search
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag=laravel-vector-search-migrations
php artisan migrate
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=laravel-vector-search-config
```

Configure `.env` for your driver and embedding provider:

**pgvector + OpenAI**

```dotenv
VECTOR_SEARCH_DRIVER=postgres_vector
VECTOR_SEARCH_EMBEDDING_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

**pgvector + Ollama (local)**

```dotenv
VECTOR_SEARCH_DRIVER=postgres_vector
VECTOR_SEARCH_EMBEDDING_PROVIDER=ollama
OLLAMA_HOST=http://localhost:11434
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
```

**Meilisearch**

```dotenv
VECTOR_SEARCH_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=masterKey
```

Start a queue worker before indexing documents in production:

```bash
php artisan queue:work
```

For pgvector, optionally publish and run the HNSW index migration stub (`add_vector_chunks_embedding_hnsw_index`) for faster similarity queries.

## Configuration

| Config key                     | Env variable                                                                                          | Default           | Description                                                   |
| ------------------------------ | ----------------------------------------------------------------------------------------------------- | ----------------- | ------------------------------------------------------------- |
| `default_driver`               | `VECTOR_SEARCH_DRIVER`                                                                                | `postgres_vector` | Default vector storage driver                                 |
| `default_embedding_provider`   | `VECTOR_SEARCH_EMBEDDING_PROVIDER`                                                                    | `openai`          | Default embedding provider                                    |
| `default_index`                | `VECTOR_SEARCH_DEFAULT_INDEX`                                                                         | `default`         | Fallback index name                                           |
| `scout.default_index`          | `VECTOR_SEARCH_SCOUT_INDEX`                                                                           | —                 | Index used by Scout engine                                    |
| `embedding_providers.openai.*` | `OPENAI_API_KEY`, `OPENAI_EMBEDDING_MODEL`, `OPENAI_EMBEDDING_BATCH_SIZE`, `OPENAI_EMBEDDING_TIMEOUT` | —                 | OpenAI embedding settings                                     |
| `embedding_providers.ollama.*` | `OLLAMA_HOST`, `OLLAMA_EMBEDDING_MODEL`, `OLLAMA_EMBEDDING_CONCURRENCY`, `OLLAMA_EMBEDDING_TIMEOUT`   | —                 | Ollama embedding settings                                     |
| `embedding_providers.cohere.*` | `COHERE_API_KEY`, `COHERE_EMBEDDING_MODEL`                                                            | —                 | Cohere settings (provider not implemented)                    |
| `drivers.pgvector.connection`  | `VECTOR_DB_CONNECTION`                                                                                | —                 | DB connection for pgvector (falls back to default connection) |
| `drivers.meilisearch.*`        | `MEILISEARCH_HOST`, `MEILISEARCH_KEY`, `MEILISEARCH_BATCH_SIZE`                                       | —                 | Meilisearch connection and index settings                     |
| `drivers.pinecone.*`           | `PINECONE_API_KEY`, `PINECONE_ENVIRONMENT`                                                            | —                 | Pinecone settings (driver not implemented)                    |
| `splitters`                    | —                                                                                                     | built-in classes  | Custom text splitter registration                             |
| `testing.embedding_dimensions` | —                                                                                                     | `8`               | Vector size for `null` embedding provider in tests            |

The config key for the pgvector connection is `drivers.pgvector`, but the driver enum value is `postgres_vector`. Both `postgres_vector` and `pgvector` resolve to the same driver in `VectorDriverManager`.

Built-in text splitters: `recursive_character`, `token_limit`, `sentence`.

## Vector drivers

Each `VectorIndex` record stores its own `driver`. The application default is `config('vector-search.default_driver')`.

| Driver              | Enum value        | Status          | Keyword search        |
| ------------------- | ----------------- | --------------- | --------------------- |
| PostgreSQL/pgvector | `postgres_vector` | Implemented     | No                    |
| Meilisearch         | `meilisearch`     | Implemented     | Yes (`keywordSearch`) |
| Pinecone            | `pinecone`        | Not implemented | —                     |

Per-index driver selection is set when creating the index:

```php
use Eloquage\LaravelVectorSearch\Enums\ChunkingStrategy;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;

VectorIndex::query()->create([
    'name' => 'document-archive',
    'driver' => IndexDriver::POSTGRES_VECTOR,
    'embedding_provider' => EmbeddingProvider::OPENAI,
    'embedding_model' => 'text-embedding-3-small',
    'dimensions' => 1536,
    'chunking_strategy' => ChunkingStrategy::RECURSIVE_CHARACTER,
    'chunk_size' => 1000,
    'chunk_overlap' => 200,
]);
```

## Embedding providers

| Provider       | Value    | Status                                   |
| -------------- | -------- | ---------------------------------------- |
| OpenAI         | `openai` | Implemented                              |
| Ollama         | `ollama` | Implemented                              |
| Null (testing) | `null`   | Implemented — deterministic fake vectors |
| Cohere         | `cohere` | Not implemented                          |

Each index stores `embedding_provider`, `embedding_model`, and optional `dimensions`. At search time, `VectorSearchIndex` resolves the embedder from the index's `embedding_provider` value.

## Indexing: queued vs sync

**Production (queued)** — default for `indexDocument()` and the `SearchableVector` trait:

```php
use Eloquage\LaravelVectorSearch\Facades\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;

LaravelVectorSearch::indexDocument($document);
// dispatches ProcessVectorDocument → EmbedVectorChunks
```

Both jobs implement `ShouldBeUnique` with exponential backoff (`[10, 60, 300]` seconds) and a 300-second timeout.

**Tests and scripts (sync)** — process immediately without a worker:

```php
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;

ProcessVectorDocument::dispatchSync($document);
```

`EmbedVectorChunks` is dispatched automatically after chunking commits.

## Quick start

### 1. Create an index

```php
use Eloquage\LaravelVectorSearch\Enums\ChunkingStrategy;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;

VectorIndex::query()->create([
    'name' => 'docs',
    'driver' => IndexDriver::POSTGRES_VECTOR,
    'embedding_provider' => EmbeddingProvider::OPENAI,
    'embedding_model' => 'text-embedding-3-small',
    'dimensions' => 1536,
    'chunking_strategy' => ChunkingStrategy::RECURSIVE_CHARACTER,
    'chunk_size' => 1000,
    'chunk_overlap' => 200,
]);
```

### 2. Add a document and index it

```php
use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Facades\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;

$document = VectorDocument::query()->create([
    'vector_index_id' => $index->id,
    'title' => 'Deployment guide',
    'content' => 'Run migrations and start a queue worker before indexing.',
    'metadata' => ['collection' => 'guides'],
    'status' => DocumentStatus::DRAFT,
]);

LaravelVectorSearch::indexDocument($document);
```

### 3. Search

```php
$chunks = LaravelVectorSearch::forIndex('docs')->search('queue worker', limit: 5);

foreach ($chunks as $chunk) {
    echo $chunk->content;
}
```

Pass a `VectorIndex` model instead of a name if you already have the record:

```php
LaravelVectorSearch::forIndex($index)->search($query);
```

### Meilisearch keyword search

When the index driver is `meilisearch`, use `keywordSearch` for full-text queries:

```php
$chunks = LaravelVectorSearch::forIndex($index)
    ->keywordSearch('deployment', limit: 20, filters: ['collection' => 'guides']);
```

## End-to-end example

This mirrors how [`eloquage/laravel-document-archive`](https://github.com/eloquage/laravel-document-archive) uses the package via `DocumentArchiveSearchService`: a named index, documents with collection metadata, semantic search through the facade, and optional Meilisearch keyword search.

```php
use Eloquage\LaravelVectorSearch\Enums\ChunkingStrategy;
use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Facades\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;

// 1. Ensure the index exists
$index = VectorIndex::query()->firstOrCreate(
    ['name' => 'document-archive'],
    [
        'driver' => IndexDriver::POSTGRES_VECTOR,
        'embedding_provider' => EmbeddingProvider::OPENAI,
        'embedding_model' => 'text-embedding-3-small',
        'dimensions' => 1536,
        'chunking_strategy' => ChunkingStrategy::RECURSIVE_CHARACTER,
        'chunk_size' => 1000,
        'chunk_overlap' => 200,
    ],
);

// 2. Create and index a document
$document = VectorDocument::query()->create([
    'vector_index_id' => $index->id,
    'title' => 'Witness statement',
    'content' => 'The defendant was observed leaving the premises at 22:15.',
    'metadata' => [
        'collection' => '479-26-0T9MTS',
        'canonical_basename' => 'witness-statement-001',
    ],
    'status' => DocumentStatus::DRAFT,
]);

ProcessVectorDocument::dispatchSync($document); // use indexDocument() + queue worker in production

// 3. Semantic search (same entry point as DocumentArchiveSearchService)
$chunks = LaravelVectorSearch::forIndex('document-archive')->search('witness statement', limit: 10);

$results = $chunks
    ->filter(fn ($chunk) => ($chunk->vectorDocument?->metadata['collection'] ?? null) === '479-26-0T9MTS')
    ->map(fn ($chunk) => [
        'content' => $chunk->content,
        'canonical_basename' => $chunk->vectorDocument?->metadata['canonical_basename'] ?? null,
    ])
    ->values();

// 4. Keyword search (Meilisearch driver only)
if ($index->driver === IndexDriver::MEILISEARCH) {
    $keywordHits = LaravelVectorSearch::forIndex($index)
        ->keywordSearch('witness', limit: 20, filters: ['collection' => '479-26-0T9MTS']);
}
```

For empty-index handling, embedding credential checks, and cosine similarity rescoring, see `DocumentArchiveSearchService` in `eloquage/laravel-document-archive`.

## Laravel Scout integration

Install Scout and set the driver:

```bash
composer require laravel/scout
```

```dotenv
SCOUT_DRIVER=vector-search
VECTOR_SEARCH_SCOUT_INDEX=docs
```

Add the trait to any Eloquent model:

```php
use Eloquage\LaravelVectorSearch\Concerns\SearchableVector;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use Searchable, SearchableVector;

    // Optional overrides:
    // vectorSearchIndexName(), vectorSearchTitle(), vectorSearchContent(), vectorSearchMetadata()
}
```

On save/delete, the trait syncs a `VectorDocument` and queues `ProcessVectorDocument`. Scout queries resolve chunk hits back to the source model via `VectorSearchEngine`, which auto-registers when Scout is installed.

When using both `Searchable` and `SearchableVector`, duplicate dispatches are deduplicated via `ShouldBeUnique` on `ProcessVectorDocument` and `EmbedVectorChunks`.

## Custom drivers and embedding providers

Register extensions in `AppServiceProvider::register()`:

```php
use Eloquage\LaravelVectorSearch\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;

LaravelVectorSearch::configure(function (VectorDriverManager $drivers, EmbeddingProviderManager $embeddings): void {
    $drivers->extend('custom', fn () => new CustomVectorDriver);
    $embeddings->extend('custom', fn () => new CustomEmbeddingProvider);
});
```

Custom text splitters can be registered in `config/vector-search.php` under `splitters` and referenced by `ChunkingStrategy` value on the index.

## Optional integrations

### Filament admin (`eloquage/filament-vector-search`)

```bash
composer require eloquage/filament-vector-search
```

Registers a dashboard, index/document resources, search playground, and pipeline widgets. See the [Filament plugin README](https://github.com/eloquage/filament-vector-search).

### Laravel AI SDK + MCP (`eloquage/laravel-vector-search-ai`)

```bash
composer require eloquage/laravel-vector-search-ai
```

Provides `KnowledgeBaseSearchTool`, `KnowledgeBaseAgent`, `AiSdkEmbeddingProvider`, and an MCP server. See the [AI package README](https://github.com/eloquage/laravel-vector-search-ai).

## Testing

```bash
composer test
composer analyse
composer format
```

Use `EmbeddingProvider::NULL_PROVIDER` and `ProcessVectorDocument::dispatchSync()` in tests to avoid external API calls. The `vector-search.testing.embedding_dimensions` config key controls null-provider vector size (default: 8).

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Security

Report vulnerabilities via the [security policy](https://github.com/eloquage/laravel-vector-search/security/policy).

## License

MIT. See [LICENSE](LICENSE.md).
