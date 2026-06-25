<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Models;

use Eloquage\LaravelVectorSearch\Enums\DocumentStatus;
use Eloquage\LaravelVectorSearch\Services\VectorDocumentPurger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $vector_index_id
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $title
 * @property string $content
 * @property array<string, mixed>|null $metadata
 * @property DocumentStatus $status
 * @property string|null $error_log
 * @property-read VectorIndex|null $vectorIndex
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

    protected static function booted(): void
    {
        static::deleting(function (VectorDocument $document): void {
            if ($document->isForceDeleting()) {
                return;
            }

            app(VectorDocumentPurger::class)->purgeChunks($document);
        });
    }

    public function vectorIndex(): BelongsTo
    {
        return $this->belongsTo(VectorIndex::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(VectorChunk::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
