<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Events;

use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Illuminate\Foundation\Events\Dispatchable;

class VectorDocumentIndexed
{
    use Dispatchable;

    public function __construct(
        public VectorDocument $document,
    ) {}
}
