<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $vector_document_id
 * @property int $chunk_index
 * @property string $content
 * @property array<float>|null $embedding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VectorDocument|null $vectorDocument
 */
class VectorChunk extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'vector_chunks';

    protected $fillable = [
        'vector_document_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'embedding' => 'array',
    ];

    public function vectorDocument(): BelongsTo
    {
        return $this->belongsTo(VectorDocument::class);
    }
}
