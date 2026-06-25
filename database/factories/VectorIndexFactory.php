<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Database\Factories;

use Eloquage\LaravelVectorSearch\Enums\ChunkingStrategy;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VectorIndex> */
class VectorIndexFactory extends Factory
{
    protected $model = VectorIndex::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'driver' => IndexDriver::POSTGRES_VECTOR,
            'embedding_provider' => EmbeddingProvider::OPENAI,
            'embedding_model' => 'text-embedding-3-small',
            'dimensions' => 1536,
            'chunking_strategy' => ChunkingStrategy::RECURSIVE_CHARACTER,
            'chunk_size' => 1000,
            'chunk_overlap' => 200,
        ];
    }
}
