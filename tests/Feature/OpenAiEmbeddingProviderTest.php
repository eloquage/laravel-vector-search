<?php

use Eloquage\LaravelVectorSearch\EmbeddingProviders\OpenAiEmbeddingProvider;
use Illuminate\Support\Facades\Http;

it('chunks large openai embedding batches', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [
                ['embedding' => [0.1], 'index' => 0],
            ],
        ]),
    ]);

    $provider = new OpenAiEmbeddingProvider(
        apiKey: 'sk-test',
        model: 'text-embedding-3-small',
        batchSize: 1,
    );

    $provider->embedBatch(['first', 'second']);

    Http::assertSentCount(2);
});
