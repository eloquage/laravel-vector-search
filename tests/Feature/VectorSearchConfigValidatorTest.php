<?php

use Eloquage\LaravelVectorSearch\Support\VectorSearchConfigValidator;
use Illuminate\Foundation\Application;

it('requires openai api key when openai is the default provider', function () {
    config()->set('vector-search.default_embedding_provider', 'openai');
    config()->set('vector-search.embedding_providers.openai.api_key', null);

    $validator = new VectorSearchConfigValidator(app(Application::class));

    expect(fn () => $validator->validate())->toThrow(InvalidArgumentException::class);
});

it('rejects unimplemented pinecone driver', function () {
    config()->set('vector-search.default_driver', 'pinecone');

    $validator = new VectorSearchConfigValidator(app(Application::class));

    expect(fn () => $validator->validate())->toThrow(InvalidArgumentException::class);
});
