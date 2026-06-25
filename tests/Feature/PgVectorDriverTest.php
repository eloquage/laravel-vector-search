<?php

use Eloquage\LaravelVectorSearch\Drivers\PgVectorDriver;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\NullEmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;

it('searches in memory when not on pgsql', function () {
    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $embedding = (new NullEmbeddingProvider)->embed('alpha query');

    VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'content' => 'alpha content',
        'embedding' => $embedding,
    ]);

    $driver = new PgVectorDriver;
    $results = $driver->search($index, $embedding, limit: 5);

    expect($results)->toHaveCount(1)
        ->and($results->first()->content)->toBe('alpha content');
});

it('returns empty results for an empty index', function () {
    $index = VectorIndex::factory()->create();

    $driver = new PgVectorDriver;
    $results = $driver->search($index, [1.0, 0.0], limit: 5);

    expect($results)->toBeEmpty();
});

it('excludes chunks from soft-deleted documents', function () {
    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
    ]);

    $activeDocument = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $deletedDocument = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $deletedDocument->delete();

    $embedding = (new NullEmbeddingProvider)->embed('query');

    VectorChunk::factory()->for($activeDocument, 'vectorDocument')->create([
        'content' => 'visible',
        'embedding' => $embedding,
    ]);

    VectorChunk::factory()->for($deletedDocument, 'vectorDocument')->create([
        'content' => 'hidden',
        'embedding' => $embedding,
    ]);

    $driver = new PgVectorDriver;
    $results = $driver->search($index, $embedding, limit: 10);

    expect($results)->toHaveCount(1)
        ->and($results->first()->content)->toBe('visible');
});
