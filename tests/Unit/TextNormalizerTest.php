<?php

use App\Services\Rules\TextNormalizer;

test('normalizes text for matching', function (?string $input, string $expected) {
    expect(TextNormalizer::normalize($input))->toBe($expected);
})->with([
    'split punctuation' => ['Takeaway .com', 'TAKEAWAY.COM'],
    'split word' => ['LOTTO He ssen', 'LOTTOHESSEN'],
    'split last letter' => ['Discover y', 'DISCOVERY'],
    'umlaut' => ['Müller', 'MUELLER'],
    'several umlauts' => ['RhönEnergie Bäderbetrieb', 'RHOENENERGIEBAEDERBETRIEB'],
    'uppercase umlaut' => ['UNI DÖNER', 'UNIDOENER'],
    'sharp s' => ['Straße', 'STRASSE'],
    'newline' => ["Ihr Einkauf\nbei", 'IHREINKAUFBEI'],
    'plus sign' => ['Netflix + Router', 'NETFLIX+ROUTER'],
    'empty' => ['', ''],
    'null' => [null, ''],
]);
