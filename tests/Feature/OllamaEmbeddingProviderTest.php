<?php

use Eloquage\LaravelVectorSearch\EmbeddingProviders\OllamaEmbeddingProvider;
use Illuminate\Support\Facades\Http;

it('embeds ollama texts concurrently', function () {
    config()->set('vector-search.embedding_providers.ollama.concurrency', 2);

    Http::fake([
        'localhost:11434/api/embeddings' => Http::response(['embedding' => [0.1, 0.2]]),
    ]);

    $provider = new OllamaEmbeddingProvider('http://localhost:11434', 'nomic-embed-text');
    $embeddings = $provider->embedBatch(['one', 'two', 'three']);

    expect($embeddings)->toHaveCount(3);
    Http::assertSentCount(3);
});
