<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Database\Factories;

use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VectorChunk> */
class VectorChunkFactory extends Factory
{
    protected $model = VectorChunk::class;

    public function definition(): array
    {
        return [
            'vector_document_id' => VectorDocument::factory(),
            'chunk_index' => fake()->numberBetween(0, 100),
            'content' => fake()->paragraph(),
            'embedding' => null,
        ];
    }
}
