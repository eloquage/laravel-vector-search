<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Contracts;

interface HasLabel
{
    public function getLabel(): string;
}
