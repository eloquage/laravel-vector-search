<?php

use Eloquage\LaravelVectorSearch\Contracts\SupportsKeywordSearch;
use Eloquage\LaravelVectorSearch\Drivers\MeilisearchDriver;
use Eloquage\LaravelVectorSearch\Drivers\PgVectorDriver;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Facades\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Search\SearchResult;

it('upserts chunk metadata to meilisearch', function () {
    $index = VectorIndex::factory()->create([
        'name' => 'document-archive',
        'driver' => IndexDriver::MEILISEARCH,
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'title' => 'chunk-a',
        'metadata' => [
            'collection' => '479-26-0T9MTS',
            'canonical_basename' => 'chunk-a',
            'source_file' => 'doc.pdf',
        ],
    ]);

    $chunk = VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'chunk_index' => 0,
        'content' => 'witness statement',
        'embedding' => [0.1, 0.2, 0.3],
    ]);

    $indexes = Mockery::mock(Indexes::class);
    $indexes->shouldReceive('addDocuments')
        ->once()
        ->with(Mockery::on(function (array $documents): bool {
            expect($documents)->toHaveCount(1)
                ->and($documents[0]['id'])->toBeString()
                ->and($documents[0]['content'])->toBe('witness statement')
                ->and($documents[0]['collection'])->toBe('479-26-0T9MTS')
                ->and($documents[0]['canonical_basename'])->toBe('chunk-a')
                ->and($documents[0]['_vectors']['default']['embeddings'][0])->toBe([0.1, 0.2, 0.3]);

            return true;
        }))
        ->andReturn(['taskUid' => 1]);

    $indexes->shouldReceive('updateSettings')->once();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getIndex')->once()->with('document-archive')->andThrow(new RuntimeException('missing'));
    $client->shouldReceive('createIndex')->once()->with('document-archive', ['primaryKey' => 'id']);
    $client->shouldReceive('index')->andReturn($indexes);

    $driver = new MeilisearchDriver('http://localhost:7700', null, $client);
    $driver->upsert($chunk);
});

it('keyword searches meilisearch and maps hits to chunks', function () {
    $index = VectorIndex::factory()->create([
        'name' => 'document-archive',
        'driver' => IndexDriver::MEILISEARCH,
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $chunk = VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'content' => 'witness statement',
    ]);

    $searchResult = Mockery::mock(SearchResult::class);
    $searchResult->shouldReceive('getHits')->andReturn([
        ['id' => $chunk->id, 'content' => 'witness statement'],
    ]);

    $indexes = Mockery::mock(Indexes::class);
    $indexes->shouldReceive('updateSettings')->once();
    $indexes->shouldReceive('search')
        ->once()
        ->with('witness', Mockery::on(function (array $params): bool {
            expect($params['limit'])->toBe(20)
                ->and($params['filter'])->toBe('collection = "479-26-0T9MTS"');

            return true;
        }))
        ->andReturn($searchResult);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getIndex')->once()->andReturn(Mockery::mock(Indexes::class));
    $client->shouldReceive('index')->andReturn($indexes);

    $driver = new MeilisearchDriver('http://localhost:7700', null, $client);

    $results = $driver->keywordSearch($index, 'witness', 20, ['collection' => '479-26-0T9MTS']);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($chunk->id);
});

it('returns empty keyword search for non-meilisearch drivers', function () {
    $index = VectorIndex::factory()->create([
        'name' => 'docs',
        'driver' => IndexDriver::POSTGRES_VECTOR,
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
    ]);

    $results = LaravelVectorSearch::forIndex($index)->keywordSearch('witness');

    expect($results)->toBeEmpty();
});

it('implements keyword search contract on meilisearch driver', function () {
    $driver = new MeilisearchDriver('http://localhost:7700');

    expect($driver)->toBeInstanceOf(MeilisearchDriver::class)
        ->and($driver)->toBeInstanceOf(SupportsKeywordSearch::class);
});

it('does not upsert chunks without embeddings', function () {
    $index = VectorIndex::factory()->create(['driver' => IndexDriver::MEILISEARCH]);
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $chunk = VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'embedding' => null,
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldNotReceive('index');

    $driver = new MeilisearchDriver('http://localhost:7700', null, $client);
    $driver->upsert($chunk);
});

it('builds filter strings for keyword search', function () {
    $index = VectorIndex::factory()->create(['driver' => IndexDriver::MEILISEARCH]);
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $chunk = VectorChunk::factory()->for($document, 'vectorDocument')->create();

    $searchResult = Mockery::mock(SearchResult::class);
    $searchResult->shouldReceive('getHits')->andReturn([]);

    $indexes = Mockery::mock(Indexes::class);
    $indexes->shouldReceive('updateSettings')->once();
    $indexes->shouldReceive('search')->once()->andReturn($searchResult);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getIndex')->once()->andReturn(Mockery::mock(Indexes::class));
    $client->shouldReceive('index')->andReturn($indexes);

    $driver = new MeilisearchDriver('http://localhost:7700', null, $client);
    $driver->keywordSearch($index, 'query', 5, [
        'collection' => '479-26-0T9MTS',
        'canonical_basename' => 'doc-a',
    ]);
});

it('pgvector driver does not support keyword search', function () {
    $driver = new PgVectorDriver;

    expect($driver)->not->toBeInstanceOf(SupportsKeywordSearch::class);
});
