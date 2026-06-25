<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Enums;

use Eloquage\LaravelVectorSearch\Contracts\HasLabel;

enum ChunkingStrategy: string implements HasLabel
{
    case RECURSIVE_CHARACTER = 'recursive_character';
    case TOKEN_LIMIT = 'token_limit';
    case SENTENCE = 'sentence';

    public function getLabel(): string
    {
        return match ($this) {
            self::RECURSIVE_CHARACTER => 'Recursive Character',
            self::TOKEN_LIMIT => 'Token Limit',
            self::SENTENCE => 'Sentence',
        };
    }
}
