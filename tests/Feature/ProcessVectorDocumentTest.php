<?php

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Jobs\EmbedVectorChunks;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Eloquage\LaravelVectorSearch\Services\VectorChunkCleanup;
use Illuminate\Support\Facades\Queue;

it('deletes all chunk rows for a document', function () {
    $index = VectorIndex::factory()->create();
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create();

    VectorChunk::factory()->for($document, 'vectorDocument')->count(2)->create();

    app(VectorChunkCleanup::class)->purgeDocumentChunks($document);

    expect(VectorChunk::query()->where('vector_document_id', $document->id)->count())->toBe(0);
});

it('rechunks document content on process', function () {
    Queue::fake([EmbedVectorChunks::class]);

    $index = VectorIndex::factory()->nullProvider()->create();

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => 'Chunk this sentence for vector search.',
        'status' => DocumentStatus::DRAFT,
    ]);

    ProcessVectorDocument::dispatchSync($document);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::EMBEDDING)
        ->and($document->chunks)->not->toBeEmpty();

    Queue::assertPushed(EmbedVectorChunks::class);
});

it('queues embedding for empty content without chunks', function () {
    Queue::fake([EmbedVectorChunks::class]);

    $index = VectorIndex::factory()->nullProvider()->create([
        'chunk_size' => 1000,
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => '',
        'status' => DocumentStatus::DRAFT,
    ]);

    ProcessVectorDocument::dispatchSync($document);

    expect($document->fresh()->status)->toBe(DocumentStatus::EMBEDDING)
        ->and($document->fresh()->chunks)->toBeEmpty();

    Queue::assertPushed(EmbedVectorChunks::class);
});

it('dispatches embedding job after processing commits', function () {
    Queue::fake([EmbedVectorChunks::class]);

    $index = VectorIndex::factory()->nullProvider()->create();
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => 'Deferred embedding dispatch test content.',
        'status' => DocumentStatus::DRAFT,
    ]);

    ProcessVectorDocument::dispatchSync($document);

    Queue::assertPushed(EmbedVectorChunks::class);
});

it('replaces stale chunks when reprocessing a document', function () {
    Queue::fake([EmbedVectorChunks::class]);

    $index = VectorIndex::factory()->nullProvider()->create();
    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => 'First version of the document content.',
        'status' => DocumentStatus::DRAFT,
    ]);

    $job = app(ProcessVectorDocument::class, ['document' => $document]);
    $job->handle(app(VectorChunkCleanup::class));

    $document->update(['content' => 'Second version with different chunk boundaries for testing.']);
    $document->refresh();

    $job = app(ProcessVectorDocument::class, ['document' => $document]);
    $job->handle(app(VectorChunkCleanup::class));

    $indices = $document->fresh()->chunks->pluck('chunk_index')->sort()->values()->all();

    expect($indices)->toBe(array_values(array_unique($indices)))
        ->and($document->fresh()->chunks->min('chunk_index'))->toBe(0);
});
