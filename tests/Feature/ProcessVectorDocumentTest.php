<?php

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Facades\Queue;

it('bulk inserts chunks when processing a document', function () {
    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
        'dimensions' => 8,
        'embedding_model' => 'null',
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => str_repeat('word ', 500),
        'status' => DocumentStatus::DRAFT,
    ]);

    ProcessVectorDocument::dispatchSync($document);

    $document->refresh();

    expect($document->chunks()->count())->toBeGreaterThan(1)
        ->and($document->status)->toBe(DocumentStatus::COMPLETED);
});

it('deduplicates process jobs for the same document', function () {
    Queue::fake();

    $document = VectorDocument::factory()->create();

    ProcessVectorDocument::dispatch($document);
    ProcessVectorDocument::dispatch($document);

    Queue::assertPushed(ProcessVectorDocument::class, 1);
});
