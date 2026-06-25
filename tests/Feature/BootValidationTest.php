<?php

use Eloquage\LaravelVectorSearch\Managers\EmbeddingProviderManager;
use Eloquage\LaravelVectorSearch\Support\VectorSearchConfigValidator;

it('collects warnings for unconfigured default embedding provider', function () {
    config()->set('vector-search.default_embedding_provider', 'openai');
    config()->set('vector-search.embedding_providers.openai.api_key', '');

    $issues = app(VectorSearchConfigValidator::class)->collectIssues();

    expect($issues)->toContain('Default embedding provider [openai] is not configured.');
});

it('throws when strict boot validation is enabled', function () {
    config()->set('vector-search.strict_boot_validation', true);
    config()->set('vector-search.default_embedding_provider', 'openai');
    config()->set('vector-search.embedding_providers.openai.api_key', '');

    expect(fn () => app(VectorSearchConfigValidator::class)->validate())
        ->toThrow(RuntimeException::class);
});

it('reports cohere provider as configured when api key is present', function () {
    config()->set('vector-search.embedding_providers.cohere.api_key', 'cohere-key');

    expect(app(EmbeddingProviderManager::class)->isConfigured('cohere'))->toBeTrue();
});
