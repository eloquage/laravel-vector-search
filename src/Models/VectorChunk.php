<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
