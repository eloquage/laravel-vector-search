<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Enums;

use Eloquage\LaravelVectorSearch\Contracts\HasLabel;

enum EmbeddingProvider: string implements HasLabel
{
    case OPENAI = 'openai';
    case COHERE = 'cohere';
    case OLLAMA = 'ollama';
    case NULL_PROVIDER = 'null';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::COHERE => 'Cohere',
            self::OLLAMA => 'Ollama',
            self::NULL_PROVIDER => 'Null (testing)',
        };
    }
}
