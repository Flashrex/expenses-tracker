<?php

namespace App\Support;

final class Money
{
    /**
     * Format cents the German way, e.g. "−1.158,20 €" or, when signed, "+1.348,19 €".
     */
    public static function format(int $cents, bool $signed = false): string
    {
        $prefix = match (true) {
            $cents < 0 => '−',
            $signed && $cents > 0 => '+',
            default => '',
        };

        return $prefix.number_format(abs($cents) / 100, 2, ',', '.').' €';
    }
}
