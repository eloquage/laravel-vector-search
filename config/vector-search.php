<?php

// config for Eloquage/LaravelVectorSearch
return [
    'default_driver' => env('VECTOR_SEARCH_DRIVER', 'postgres_vector'),

    'default_embedding_provider' => env('VECTOR_SEARCH_EMBEDDING_PROVIDER', 'openai'),

    'default_index' => env('VECTOR_SEARCH_DEFAULT_INDEX', 'default'),

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
        ],
    ],

    'drivers' => [
        'pgvector' => [
            'connection' => env('VECTOR_DB_CONNECTION'),
        ],
        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
            'key' => env('MEILISEARCH_KEY'),
        ],
        'pinecone' => [
            'api_key' => env('PINECONE_API_KEY'),
            'environment' => env('PINECONE_ENVIRONMENT'),
        ],
    ],

    'scout' => [
        'default_index' => env('VECTOR_SEARCH_SCOUT_INDEX'),
    ],

    'testing' => [
        'embedding_dimensions' => 8,
    ],
];
