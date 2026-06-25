<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Services;

use Eloquage\LaravelVectorSearch\Managers\VectorDriverManager;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;

class VectorDocumentPurger
{
    public function __construct(
        protected VectorDriverManager $drivers,
    ) {}

    public function purge(VectorDocument $document): void
    {
        $this->purgeChunks($document);

        $document->delete();
    }

    public function purgeChunks(VectorDocument $document): void
    {
        $document->loadMissing('vectorIndex', 'chunks');

        if ($document->vectorIndex !== null && $document->chunks->isNotEmpty()) {
            /** @var VectorIndex $vectorIndex */
            $vectorIndex = $document->vectorIndex;
            $driver = $this->drivers->driver($vectorIndex->driver->value);
            $driver->deleteByDocument($document);
        }

        $document->chunks()->delete();
    }
}
