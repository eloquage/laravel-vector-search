<?php

use Eloquage\LaravelVectorSearch\EmbeddingProviders\OpenAiEmbeddingProvider;
use Illuminate\Support\Facades\Http;

it('batches openai embedding requests', function () {
    config()->set('vector-search.embedding_providers.openai.batch_size', 100);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::sequence()
            ->push(['data' => array_map(fn (int $i): array => ['index' => $i, 'embedding' => [0.1, 0.2]], range(0, 99))])
            ->push(['data' => array_map(fn (int $i): array => ['index' => $i, 'embedding' => [0.3, 0.4]], range(0, 49))]),
    ]);

    $provider = new OpenAiEmbeddingProvider('test-key', 'text-embedding-3-small');
    $texts = array_map(fn (int $i): string => "text {$i}", range(1, 150));

    $embeddings = $provider->embedBatch($texts);

    expect($embeddings)->toHaveCount(150);
    Http::assertSentCount(2);
});

it('retries openai embedding requests on rate limit responses', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::sequence()
            ->push(status: 429)
            ->push(['data' => [['index' => 0, 'embedding' => [0.1, 0.2]]]]),
    ]);

    $provider = new OpenAiEmbeddingProvider('test-key', 'text-embedding-3-small');
    $embeddings = $provider->embedBatch(['hello']);

    expect($embeddings)->toHaveCount(1);
    Http::assertSentCount(2);
});
