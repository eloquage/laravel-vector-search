<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Support;

use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;

class ChunkMetadataMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function documentMetadata(VectorDocument $document): array
    {
        $metadata = $document->getAttribute('metadata');

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toMeilisearchPayload(VectorChunk $chunk, VectorDocument $document): array
    {
        $metadata = self::documentMetadata($document);

        return [
            'id' => (string) $chunk->id,
            'content' => $chunk->content,
            'chunk_index' => $chunk->chunk_index,
            'title' => $document->title,
            'collection' => $metadata['collection'] ?? null,
            'case_number' => $metadata['case_number'] ?? $metadata['collection'] ?? null,
            'canonical_basename' => $metadata['canonical_basename'] ?? null,
            'source_file' => $metadata['source_file'] ?? null,
            '_vectors' => [
                'default' => [
                    'embeddings' => [$chunk->embedding],
                ],
            ],
        ];
    }
}
