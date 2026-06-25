<?php

use Eloquage\LaravelVectorSearch\Drivers\MeilisearchDriver;
use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Eloquage\LaravelVectorSearch\Services\VectorDocumentPurger;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

function bindMeilisearchDriver(Client $client): MeilisearchDriver
{
    $driver = new MeilisearchDriver('http://localhost:7700', null, $client);
    $manager = app(VectorDriverManager::class);
    $manager->extend('meilisearch', fn (): MeilisearchDriver => $driver);
    $manager->forgetDrivers();

    return $driver;
}

it('purges sql chunks and meilisearch documents', function () {
    $index = VectorIndex::factory()->create(['driver' => IndexDriver::MEILISEARCH]);
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'embedding' => [0.1, 0.2],
    ]);

    $indexes = Mockery::mock(Indexes::class);
    $indexes->shouldReceive('updateSettings')->once();
    $indexes->shouldReceive('deleteDocuments')->once()->andReturn(['taskUid' => 1]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getIndex')->once()->andReturn(Mockery::mock(Indexes::class));
    $client->shouldReceive('index')->andReturn($indexes);

    bindMeilisearchDriver($client);

    app(VectorDocumentPurger::class)->purgeChunks($document->fresh());

    expect($document->chunks()->count())->toBe(0);
});

it('re-indexing purges old meilisearch chunks before creating new ones', function () {
    $index = VectorIndex::factory()->create([
        'driver' => IndexDriver::MEILISEARCH,
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
        'dimensions' => 8,
        'embedding_model' => 'null',
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => 'First version of the document content.',
        'status' => DocumentStatus::DRAFT,
    ]);

    $deleteCalls = 0;

    $indexes = Mockery::mock(Indexes::class);
    $indexes->shouldReceive('updateSettings')->andReturn(['taskUid' => 1]);
    $indexes->shouldReceive('deleteDocuments')->andReturnUsing(function () use (&$deleteCalls) {
        $deleteCalls++;

        return ['taskUid' => 1];
    });
    $indexes->shouldReceive('addDocuments')->andReturn(['taskUid' => 1]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getIndex')->andThrow(new RuntimeException('missing'));
    $client->shouldReceive('createIndex')->andReturn(['taskUid' => 1]);
    $client->shouldReceive('index')->andReturn($indexes);

    bindMeilisearchDriver($client);

    ProcessVectorDocument::dispatchSync($document);

    $firstChunkIds = $document->fresh()->chunks->pluck('id')->all();

    ProcessVectorDocument::dispatchSync($document->fresh());

    expect($document->fresh()->chunks->pluck('id')->all())->not->toEqual($firstChunkIds)
        ->and($deleteCalls)->toBeGreaterThanOrEqual(1);
});
