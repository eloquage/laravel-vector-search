<?php

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
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

    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
    ]);

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

    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
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
