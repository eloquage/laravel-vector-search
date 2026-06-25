<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Observers;

use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Services\VectorChunkCleanup;

class VectorDocumentObserver
{
    public function __construct(
        private VectorChunkCleanup $cleanup,
    ) {}

    public function deleting(VectorDocument $document): void
    {
        $this->cleanup->purgeDocumentChunks($document);
    }

    public function forceDeleting(VectorDocument $document): void
    {
        $this->cleanup->purgeDocumentChunks($document);
    }
}
