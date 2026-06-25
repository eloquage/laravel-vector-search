<?php

use Eloquage\LaravelVectorSearch\Drivers\PgVectorDriver;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;

it('deleteByDocument removes chunk rows', function () {
    $index = VectorIndex::factory()->create();
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();
    VectorChunk::factory()->for($document, 'vectorDocument')->count(2)->create();

    expect($document->chunks()->count())->toBe(2);

    (new PgVectorDriver)->deleteByDocument($document);

    expect($document->chunks()->count())->toBe(0);
});

it('delete removes a single chunk row', function () {
    $document = VectorDocument::factory()->create();
    $chunk = VectorChunk::factory()->for($document, 'vectorDocument')->create();

    (new PgVectorDriver)->delete($chunk);

    expect(VectorChunk::query()->find($chunk->id))->toBeNull();
});
