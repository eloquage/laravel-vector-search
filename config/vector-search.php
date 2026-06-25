<?php

use Eloquage\LaravelVectorSearch\Services\Chunking\RecursiveCharacterSplitter;
use Eloquage\LaravelVectorSearch\Services\Chunking\SentenceSplitter;
use Eloquage\LaravelVectorSearch\Services\Chunking\TokenLimitSplitter;

// config for Eloquage/LaravelVectorSearch
return [
    'default_driver' => env('VECTOR_SEARCH_DRIVER', 'postgres_vector'),

    'default_embedding_provider' => env('VECTOR_SEARCH_EMBEDDING_PROVIDER', 'openai'),

    'default_index' => env('VECTOR_SEARCH_DEFAULT_INDEX', 'default'),

    'embedding_providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'batch_size' => (int) env('OPENAI_EMBEDDING_BATCH_SIZE', 100),
            'timeout' => (int) env('OPENAI_EMBEDDING_TIMEOUT', 60),
        ],
        'cohere' => [
            'api_key' => env('COHERE_API_KEY'),
            'model' => env('COHERE_EMBEDDING_MODEL', 'embed-english-v3.0'),
        ],
        'ollama' => [
            'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
            'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
            'concurrency' => (int) env('OLLAMA_EMBEDDING_CONCURRENCY', 4),
            'timeout' => (int) env('OLLAMA_EMBEDDING_TIMEOUT', 60),
        ],
    ],

    'drivers' => [
        'pgvector' => [
            'connection' => env('VECTOR_DB_CONNECTION'),
        ],
        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
            'key' => env('MEILISEARCH_KEY'),
            'batch_size' => (int) env('MEILISEARCH_BATCH_SIZE', 100),
            'filterable_attributes' => [
                'vector_document_id',
                'chunk_index',
                'title',
                'collection',
                'case_number',
                'canonical_basename',
                'source_file',
            ],
            'searchable_attributes' => [
                'content',
                'title',
                'canonical_basename',
            ],
        ],
        'pinecone' => [
            'api_key' => env('PINECONE_API_KEY'),
            'environment' => env('PINECONE_ENVIRONMENT'),
        ],
    ],

    'splitters' => [
        'recursive_character' => RecursiveCharacterSplitter::class,
        'token_limit' => TokenLimitSplitter::class,
        'sentence' => SentenceSplitter::class,
    ],

    'scout' => [
        'default_index' => env('VECTOR_SEARCH_SCOUT_INDEX'),
    ],

    'testing' => [
        'embedding_dimensions' => 8,
    ],
];
