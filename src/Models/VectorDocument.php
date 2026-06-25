<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Models;

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $vector_index_id
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $title
 * @property string|null $content
 * @property array<string, mixed>|null $metadata
 * @property DocumentStatus $status
 * @property string|null $error_log
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read VectorIndex|null $vectorIndex
 * @property-read Collection<int, VectorChunk> $chunks
 */
class VectorDocument extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public int $totalChunks {
        get => $this->chunks()->count();
    }

    protected $table = 'vector_documents';

    protected $fillable = [
        'vector_index_id',
        'source_type',
        'source_id',
        'title',
        'content',
        'metadata',
        'status',
        'error_log',
    ];

    protected $casts = [
        'metadata' => 'array',
        'status' => DocumentStatus::class,
    ];

    /**
     * @return BelongsTo<VectorIndex, $this>
     */
    public function vectorIndex(): BelongsTo
    {
        return $this->belongsTo(VectorIndex::class);
    }

    /**
     * @return HasMany<VectorChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(VectorChunk::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
