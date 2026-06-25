<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Observers;

use Eloquage\LaravelVectorSearch\Models\VectorIndex;

class VectorIndexObserver
{
    public function saving(VectorIndex $index): void
    {
        if ($index->dimensions === null) {
            $index->dimensions = (int) config('vector-search.index_defaults.dimensions', 1536);
        }
    }
}
