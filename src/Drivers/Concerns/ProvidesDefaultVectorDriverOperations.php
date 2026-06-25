<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Drivers\Concerns;

use Eloquage\LaravelVectorSearch\Models\VectorDocument;

trait ProvidesDefaultVectorDriverOperations
{
    public function upsertMany(iterable $chunks): void
    {
        foreach ($chunks as $chunk) {
            $this->upsert($chunk);
        }
    }

    public function deleteMany(iterable $chunks): void
    {
        foreach ($chunks as $chunk) {
            $this->delete($chunk);
        }
    }

    public function deleteByDocument(VectorDocument $document): void
    {
        $document->loadMissing('chunks');

        $this->deleteMany($document->chunks);
    }
}
