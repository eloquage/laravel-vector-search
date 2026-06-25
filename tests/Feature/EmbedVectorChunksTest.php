<?php

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Events\VectorDocumentIndexed;
use Eloquage\LaravelVectorSearch\Jobs\EmbedVectorChunks;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

it('completes embedding and dispatches indexed event', function () {
    Event::fake([VectorDocumentIndexed::class]);

    $index = VectorIndex::factory()->nullProvider()->create();

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => 'Short content for embedding.',
        'status' => DocumentStatus::DRAFT,
    ]);

    ProcessVectorDocument::dispatchSync($document);
    EmbedVectorChunks::dispatchSync($document->fresh());

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::COMPLETED)
        ->and($document->chunks)->not->toBeEmpty();

    Event::assertDispatched(VectorDocumentIndexed::class);
});

it('marks document failed when embedding throws', function () {
    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::OPENAI,
    ]);

    config()->set('vector-search.embedding_providers.openai.api_key', '');

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => 'Content',
        'status' => DocumentStatus::EMBEDDING,
    ]);

    $document->chunks()->create([
        'chunk_index' => 0,
        'content' => 'chunk',
        'embedding' => null,
    ]);

    expect(fn () => EmbedVectorChunks::dispatchSync($document))->toThrow(RuntimeException::class);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::FAILED);
});

it('implements should be unique per document', function () {
    $document = VectorDocument::factory()->create();

    $job = new ProcessVectorDocument($document);

    expect($job->uniqueId())->toBe($document->id);
});

it('rejects null embedding provider outside local and testing environments', function () {
    $index = VectorIndex::factory()->nullProvider()->create();

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'status' => DocumentStatus::EMBEDDING,
    ]);

    $document->chunks()->create([
        'chunk_index' => 0,
        'content' => 'chunk',
        'embedding' => null,
    ]);

    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => EmbedVectorChunks::dispatchSync($document))
        ->toThrow(RuntimeException::class);
});

it('does not leave completed status when embedding batch size mismatches', function () {
    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::OPENAI,
    ]);

    config()->set('vector-search.embedding_providers.openai.api_key', 'sk-test');

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'status' => DocumentStatus::EMBEDDING,
    ]);

    $document->chunks()->create([
        'chunk_index' => 0,
        'content' => 'chunk one',
        'embedding' => null,
    ]);

    $document->chunks()->create([
        'chunk_index' => 1,
        'content' => 'chunk two',
        'embedding' => null,
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [
                ['embedding' => [0.1, 0.2], 'index' => 0],
            ],
        ]),
    ]);

    expect(fn () => EmbedVectorChunks::dispatchSync($document))->toThrow(RuntimeException::class);

    expect($document->fresh()->status)->toBe(DocumentStatus::FAILED);
});
