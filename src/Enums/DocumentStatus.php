<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Enums;

use Eloquage\LaravelVectorSearch\Contracts\HasLabel;

enum DocumentStatus: string implements HasLabel
{
    case DRAFT = 'draft';
    case PROCESSING = 'processing';
    case EMBEDDING = 'embedding';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PROCESSING => 'Processing',
            self::EMBEDDING => 'Embedding',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }
}
