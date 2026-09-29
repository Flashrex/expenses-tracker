<?php

namespace App\Services\Rules;

final class TextNormalizer
{
    /**
     * Uppercase, fold umlauts and drop all whitespace so "Takeaway .com" matches "Takeaway.com".
     */
    public static function normalize(?string $text): string
    {
        $text = mb_strtoupper($text ?? '');
        $text = strtr($text, ['Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ẞ' => 'SS', 'ß' => 'SS']);

        return preg_replace('/\s+/u', '', $text);
    }
}
