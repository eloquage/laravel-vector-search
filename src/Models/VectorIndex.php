<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Models;

use Eloquage\LaravelVectorSearch\Enums\ChunkingStrategy;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Enums\IndexDriver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property IndexDriver $driver
 * @property EmbeddingProvider $embedding_provider
 * @property string|null $embedding_model
 * @property int|null $dimensions
 * @property ChunkingStrategy $chunking_strategy
 * @property int $chunk_size
 * @property int $chunk_overlap
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, VectorDocument> $documents
 */
class VectorIndex extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public int $totalDocuments {
        get => $this->documents()->count();
    }

    protected $table = 'vector_indexes';

    protected $fillable = [
        'name',
        'driver',
        'embedding_provider',
        'embedding_model',
        'dimensions',
        'chunking_strategy',
        'chunk_size',
        'chunk_overlap',
    ];

    protected $casts = [
        'driver' => IndexDriver::class,
        'embedding_provider' => EmbeddingProvider::class,
        'chunking_strategy' => ChunkingStrategy::class,
        'dimensions' => 'integer',
        'chunk_size' => 'integer',
        'chunk_overlap' => 'integer',
    ];

    /**
     * @return HasMany<VectorDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(VectorDocument::class);
    }
}
