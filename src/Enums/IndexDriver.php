<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Enums;

use Eloquage\LaravelVectorSearch\Contracts\HasLabel;

enum IndexDriver: string implements HasLabel
{
    case POSTGRES_VECTOR = 'postgres_vector';
    case MEILISEARCH = 'meilisearch';
    case PINECONE = 'pinecone';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::POSTGRES_VECTOR => 'Postgres Vector',
            self::MEILISEARCH => 'Meilisearch',
            self::PINECONE => 'Pinecone',
        };
    }
}
