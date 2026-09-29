<?php

use App\Services\Statements\MerchantDeriver;

test('derives merchants', function (string $type, ?string $counterparty, string $purpose, string $expected) {
    expect((new MerchantDeriver)->derive($type, $counterparty, $purpose))->toBe($expected);
})->with([
    'visa branch' => ['Lastschrift', 'VISA TEGUT FILIALE 5020', '', 'TEGUT'],
    'visa code' => ['Lastschrift', 'VISA AMAZON* NQ0NU61X4', '', 'AMAZON'],
    'visa dotted code' => ['Lastschrift', 'VISA WWW.AMAZON.* NL8J71384', '', 'WWW.AMAZON'],
    'visa prime code' => ['Lastschrift', 'VISA AMAZON PRIM* BZ41E2GO5', '', 'AMAZON PRIM'],
    'visa paypal' => ['Lastschrift', 'VISA PAYPAL *STEAM GAMES', '', 'STEAM GAMES'],
    'visa uzr' => ['Lastschrift', 'VISA UZR*RISTORANTE LA ROMA', '', 'RISTORANTE LA ROMA'],
    'visa store number' => ['Lastschrift', 'VISA ROSSMANN 3290', '', 'ROSSMANN'],
    'visa city' => ['Lastschrift', 'VISA UNI DONER, FULDA', '', 'UNI DONER'],
    'visa gmbh &' => ['Lastschrift', 'VISA BAECKEREI HAPP GMBH &', '', 'BAECKEREI HAPP'],
    'visa gmbh' => ['Lastschrift', 'VISA LS CHUMBOS FULDA GMBH', '', 'LS CHUMBOS FULDA'],
    'visa plain' => ['Lastschrift', 'VISA REWE KAI UWE GRASMUECK', '', 'REWE KAI UWE GRASMUECK'],
    'paypal purchase' => [
        'Lastschrift',
        'PayPal Europe S.a.r.l. et Cie S.C.A',
        "1050619809280/PP.5741.PP/. Spotify AB, Ihr Einkauf bei\nSpotify AB\nMandat: 5QD22259HS784\nReferenz: 1050619809280",
        'Spotify',
    ],
    'paypal without purchase' => ['Lastschrift', 'PayPal Europe S.a.r.l. et Cie S.C.A', 'foo', 'PayPal Europe S.a.r.l. et Cie'],
    'direct debit' => ['Lastschrift', 'RhoenEnergie Fulda GmbH', '', 'RhoenEnergie Fulda'],
    'split gmbh' => ['Gehalt/Rente', 'CGS Clinical Guideline Serv ices Gm bH', '', 'CGS Clinical Guideline Serv ices'],
    'purpose' => ['Dauerauftrag/Terminueberw.', null, 'Netflix + Router', 'Netflix + Router'],
    'type' => ['Echtzeitüberweisung', null, '', 'Echtzeitüberweisung'],
    'long purpose' => ['Dauerauftrag/Terminueberw.', null, str_repeat('x', 80), str_repeat('x', 60)],
]);
