<?php

use Eloquage\LaravelVectorSearch\Drivers\PineconeDriver;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

it('upserts vectors to pinecone in batch', function () {
    Http::fake([
        'https://example-index.pinecone.io/*' => Http::response(['upsertedCount' => 1]),
    ]);

    $index = VectorIndex::factory()->create([
        'name' => 'document-archive',
        'driver' => IndexDriver::PINECONE,
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'metadata' => ['collection' => '479-26-0T9MTS'],
    ]);

    $chunk = VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'embedding' => [0.1, 0.2, 0.3],
    ]);

    $driver = new PineconeDriver(
        apiKey: 'pinecone-key',
        indexHost: 'https://example-index.pinecone.io',
    );

    $driver->upsertBatch(new Collection([$chunk]));

    Http::assertSent(function ($request) use ($chunk): bool {
        $body = $request->data();

        return str_contains($request->url(), '/vectors/upsert')
            && $body['namespace'] === 'document-archive'
            && $body['vectors'][0]['id'] === (string) $chunk->id;
    });
});

it('queries pinecone and maps matches to chunks', function () {
    $index = VectorIndex::factory()->create([
        'name' => 'semantic-index',
        'driver' => IndexDriver::PINECONE,
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $chunk = VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'embedding' => [0.1, 0.2, 0.3],
    ]);

    Http::fake([
        'https://example-index.pinecone.io/*' => Http::response([
            'matches' => [
                ['id' => $chunk->id, 'score' => 0.99],
            ],
        ]),
    ]);

    $driver = new PineconeDriver(
        apiKey: 'pinecone-key',
        indexHost: 'https://example-index.pinecone.io',
    );

    $results = $driver->search($index, [0.1, 0.2, 0.3], limit: 3);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($chunk->id);
});

it('deletes vectors from pinecone', function () {
    $index = VectorIndex::factory()->create([
        'name' => 'document-archive',
        'driver' => IndexDriver::PINECONE,
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $chunk = VectorChunk::factory()->for($document, 'vectorDocument')->create();

    Http::fake([
        'https://example-index.pinecone.io/*' => Http::response([]),
    ]);

    $driver = new PineconeDriver(
        apiKey: 'pinecone-key',
        indexHost: 'https://example-index.pinecone.io',
    );

    $driver->delete($chunk);

    Http::assertSent(function ($request) use ($chunk): bool {
        return str_contains($request->url(), '/vectors/delete')
            && $request->data()['ids'] === [(string) $chunk->id];
    });
});
