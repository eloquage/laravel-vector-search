<?php

use Eloquage\LaravelVectorSearch\EmbeddingProviders\CohereEmbeddingProvider;
use Eloquage\LaravelVectorSearch\EmbeddingProviders\OpenAiEmbeddingProvider;
use Illuminate\Support\Facades\Http;

it('batches openai embedding requests', function () {
    config()->set('vector-search.embedding_batch_size', 2);

    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['data' => [
                ['embedding' => [0.1, 0.2], 'index' => 0],
                ['embedding' => [0.3, 0.4], 'index' => 1],
            ]])
            ->push(['data' => [
                ['embedding' => [0.5, 0.6], 'index' => 0],
            ]]),
    ]);

    $provider = new OpenAiEmbeddingProvider(
        apiKey: 'sk-test',
        model: 'text-embedding-3-small',
        batchSize: 2,
        timeout: 30,
    );

    $vectors = $provider->embedBatch(['one', 'two', 'three']);

    expect($vectors)->toHaveCount(3);

    Http::assertSentCount(2);
});

it('uses search query input type for cohere query embeddings', function () {
    Http::fake([
        'api.cohere.com/*' => Http::response([
            'embeddings' => [
                'float' => [[0.1, 0.2, 0.3]],
            ],
        ]),
    ]);

    $provider = new CohereEmbeddingProvider(
        apiKey: 'cohere-test',
        model: 'embed-english-v3.0',
        batchSize: 100,
        timeout: 30,
    );

    $vector = $provider->embedQuery('search query');

    expect($vector)->toBe([0.1, 0.2, 0.3]);

    Http::assertSent(function ($request): bool {
        return $request->data()['input_type'] === 'search_query';
    });
});

it('uses search document input type for cohere batch embeddings', function () {
    Http::fake([
        'api.cohere.com/*' => Http::response([
            'embeddings' => [
                'float' => [[0.1], [0.2]],
            ],
        ]),
    ]);

    $provider = new CohereEmbeddingProvider(
        apiKey: 'cohere-test',
        model: 'embed-english-v3.0',
    );

    $vectors = $provider->embedBatch(['doc one', 'doc two']);

    expect($vectors)->toHaveCount(2);

    Http::assertSent(function ($request): bool {
        return $request->data()['input_type'] === 'search_document';
    });
});
