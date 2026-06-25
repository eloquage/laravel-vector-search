<?php

use Eloquage\LaravelVectorSearch\Drivers\PgVectorDriver;
use Eloquage\LaravelVectorSearch\Drivers\PineconeDriver;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Meilisearch\Client;

it('throws when meilisearch php client is missing', function () {
    if (class_exists(Client::class)) {
        expect(true)->toBeTrue();

        return;
    }

    config()->set('vector-search.default_driver', IndexDriver::MEILISEARCH->value);

    expect(fn () => app(VectorDriverManager::class)->driver())
        ->toThrow(RuntimeException::class);
});

it('creates postgres vector driver by default', function () {
    $driver = app(VectorDriverManager::class)->driver();

    expect($driver)->toBeInstanceOf(PgVectorDriver::class);
});

it('creates pinecone driver when configured', function () {
    config()->set('vector-search.drivers.pinecone.api_key', 'key');
    config()->set('vector-search.drivers.pinecone.index_host', 'https://example.pinecone.io');

    $driver = app(VectorDriverManager::class)->driver(IndexDriver::PINECONE->value);

    expect($driver)->toBeInstanceOf(PineconeDriver::class);
});
