<?php

use Eloquage\LaravelVectorSearch\EmbeddingProviders\NullEmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Facades\LaravelVectorSearch;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('returns empty collection when index has no embedded chunks', function () {
    $index = VectorIndex::factory()->create([
        'name' => 'empty-index',
        'embedding_provider' => EmbeddingProvider::OPENAI,
    ]);

    config()->set('vector-search.embedding_providers.openai.api_key', 'sk-test');

    $results = LaravelVectorSearch::forIndex($index)->search('query', limit: 5);

    expect($results)->toBeEmpty();
});

it('throws when index name does not exist', function () {
    expect(fn () => LaravelVectorSearch::forIndex('missing-index')->search('query'))
        ->toThrow(ModelNotFoundException::class);
});

it('searches when embedded chunks exist', function () {
    $index = VectorIndex::factory()->create([
        'name' => 'populated',
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    $embedding = (new NullEmbeddingProvider)->embed('deployment');

    VectorChunk::factory()->for($document, 'vectorDocument')->create([
        'embedding' => $embedding,
        'content' => 'Deploy with queue workers.',
    ]);

    $results = LaravelVectorSearch::forIndex('populated')->search('deployment', limit: 3);

    expect($results)->toHaveCount(1);
});
