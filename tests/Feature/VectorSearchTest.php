<?php

use Eloquage\LaravelVectorSearch\EmbeddingProviders\NullEmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Facades\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Eloquage\LaravelVectorSearch\Services\Chunking\RecursiveCharacterSplitter;

it('resolves the vector search service from the container', function () {
    expect(app(Eloquage\LaravelVectorSearch\LaravelVectorSearch::class))->toBeInstanceOf(
        Eloquage\LaravelVectorSearch\LaravelVectorSearch::class
    );
});

it('creates null embeddings deterministically', function () {
    $provider = new NullEmbeddingProvider;

    expect($provider->embed('hello'))
        ->toBe($provider->embed('hello'))
        ->not->toBe($provider->embed('world'));
});

it('splits text recursively', function () {
    $splitter = new RecursiveCharacterSplitter;
    $chunks = $splitter->split(str_repeat('word ', 500), chunkSize: 100, chunkOverlap: 20);

    expect($chunks)->not->toBeEmpty();
    expect(strlen($chunks[0]))->toBeLessThanOrEqual(120);
});

it('dispatches embedding after chunking a document', function () {
    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
    ]);
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => 'Laravel makes vector search approachable.',
        'status' => DocumentStatus::DRAFT,
    ]);

    ProcessVectorDocument::dispatchSync($document);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::COMPLETED);
    expect($document->chunks)->toHaveCount(1);
    expect($document->chunks->first()->embedding)->not->toBeNull();
});

it('searches an index through the facade', function () {
    $index = VectorIndex::factory()->create([
        'name' => 'docs',
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
    ]);
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'title' => 'Deployment guide',
        'status' => DocumentStatus::COMPLETED,
    ]);

    $embedding = (new NullEmbeddingProvider)->embed('deployment');

    VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'chunk_index' => 0,
        'content' => 'Deploy with artisan migrate and queue workers.',
        'embedding' => $embedding,
    ]);

    $results = LaravelVectorSearch::forIndex('docs')->search('deployment', limit: 3);

    expect($results)->toHaveCount(1);
    expect($results->first()->content)->toContain('Deploy');
});
