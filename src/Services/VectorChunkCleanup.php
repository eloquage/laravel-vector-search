<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Services;

use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorChunk;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Database\Eloquent\Collection;

class VectorChunkCleanup
{
    public function __construct(
        private VectorDriverManager $drivers,
    ) {}

    /**
     * @param  Collection<int, VectorChunk>  $chunks
     */
    public function purgeFromDriver(Collection $chunks, VectorIndex $index): void
    {
        if ($chunks->isEmpty()) {
            return;
        }

        $driver = $this->drivers->driver($index->driver->value);

        foreach ($chunks as $chunk) {
            $driver->delete($chunk);
        }
    }

    public function purgeDocumentChunks(VectorDocument $document): void
    {
        $document->loadMissing('vectorIndex');

        $index = $document->vectorIndex;

        if ($index === null) {
            $document->chunks()->delete();

            return;
        }

        $chunks = $document->chunks()->get();

        $this->purgeFromDriver($chunks, $index);

        $document->chunks()->delete();
    }
}
