<?php

declare(strict_types=1);

namespace Eloquage\LaravelVectorSearch\Support;

class CosineSimilarity
{
    /**
     * @param  array<float>  $left
     * @param  array<float>  $right
     */
    public static function distance(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 1.0;
        }

        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;
        $length = min(count($left), count($right));

        for ($i = 0; $i < $length; $i++) {
            $dot += $left[$i] * $right[$i];
            $leftMagnitude += $left[$i] ** 2;
            $rightMagnitude += $right[$i] ** 2;
        }

        if ($leftMagnitude === 0.0 || $rightMagnitude === 0.0) {
            return 1.0;
        }

        return 1 - ($dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude)));
    }

    /**
     * @param  array<float>  $left
     * @param  array<float>  $right
     */
    public static function score(array $left, array $right): float
    {
        return 1 - self::distance($left, $right);
    }
}
