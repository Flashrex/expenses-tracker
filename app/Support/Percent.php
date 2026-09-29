<?php

namespace App\Support;

final class Percent
{
    /**
     * Format a percentage the German way, e.g. "22,9 %".
     */
    public static function format(float $value, int $decimals = 1): string
    {
        return number_format($value, $decimals, ',', '.').' %';
    }
}
