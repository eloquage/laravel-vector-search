<?php

// config for Eloquage/LaravelVectorSearch
return [
    'default_driver' => env('VECTOR_SEARCH_DRIVER', 'postgres_vector'),

    'default_embedding_provider' => env('VECTOR_SEARCH_EMBEDDING_PROVIDER', 'openai'),

    'default_index' => env('VECTOR_SEARCH_DEFAULT_INDEX', 'default'),

    'allow_null_embedding_provider' => (bool) env('VECTOR_SEARCH_ALLOW_NULL_EMBEDDING', false),

    'strict_boot_validation' => (bool) env('VECTOR_SEARCH_STRICT_BOOT_VALIDATION', false),

    'embedding_batch_size' => (int) env('VECTOR_SEARCH_EMBEDDING_BATCH_SIZE', 100),

    'embedding_timeout' => (int) env('VECTOR_SEARCH_EMBEDDING_TIMEOUT', 60),

    'embedding_providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
        'cohere' => [
            'api_key' => env('COHERE_API_KEY'),
            'model' => env('COHERE_EMBEDDING_MODEL', 'embed-english-v3.0'),
        ],
        'ollama' => [
            'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
            'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
            'concurrency' => (int) env('OLLAMA_EMBEDDING_CONCURRENCY', 4),
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
        ],
        'pinecone' => [
            'api_key' => env('PINECONE_API_KEY'),
            'index_host' => env('PINECONE_INDEX_HOST'),
        ],
    ],

    'splitters' => [
        // 'custom_strategy' => \App\VectorSearch\CustomSplitter::class,
    ],

    'scout' => [
        'default_index' => env('VECTOR_SEARCH_SCOUT_INDEX'),
    ],

    'index_defaults' => [
        'dimensions' => (int) env('VECTOR_SEARCH_INDEX_DIMENSIONS', 1536),
    ],

    'testing' => [
        'embedding_dimensions' => 8,
    ],
];
