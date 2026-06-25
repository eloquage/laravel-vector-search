<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Database\Factories;

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VectorDocument> */
class VectorDocumentFactory extends Factory
{
    protected $model = VectorDocument::class;

    public function definition(): array
    {
        return [
            'vector_index_id' => VectorIndex::factory(),
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'metadata' => [],
            'status' => DocumentStatus::DRAFT,
        ];
    }
}
