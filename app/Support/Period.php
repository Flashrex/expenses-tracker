<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class Period
{
    /**
     * "2026-06" → "June 2026".
     */
    public static function label(string $period): string
    {
        return self::format($period, 'MMMM YYYY');
    }

    /**
     * "2026-06" → "Jun 2026".
     */
    public static function short(string $period): string
    {
        return self::format($period, 'MMM YYYY');
    }

    private static function format(string $period, string $format): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $period)->locale('en')->isoFormat($format);
    }
}
