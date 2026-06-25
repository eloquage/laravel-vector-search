<?php

use Eloquage\LaravelVectorSearch\Contracts\EmbeddingProviderInterface;
use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Jobs\EmbedVectorChunks;
use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;

it('fails when embedding dimensions do not match the index', function () {
    $index = VectorIndex::factory()->create([
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
        'dimensions' => 8,
        'embedding_model' => 'null',
    ]);

    $document = VectorDocument::factory()->for($index, 'vectorIndex')->create([
        'content' => 'short text',
        'status' => DocumentStatus::EMBEDDING,
    ]);

    $document->chunks()->create([
        'chunk_index' => 0,
        'content' => 'short text',
        'embedding' => null,
    ]);

    $provider = new class implements EmbeddingProviderInterface
    {
        public function embed(string $text): array
        {
            return [0.1, 0.2];
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn (): array => [0.1, 0.2], $texts);
        }
    };

    $manager = Mockery::mock(EmbeddingProviderManager::class)->makePartial();
    $manager->shouldReceive('forIndex')->andReturn($provider);
    app()->instance(EmbeddingProviderManager::class, $manager);

    expect(fn () => EmbedVectorChunks::dispatchSync($document))
        ->toThrow(InvalidArgumentException::class);

    expect($document->fresh()->status)->toBe(DocumentStatus::FAILED);
});
