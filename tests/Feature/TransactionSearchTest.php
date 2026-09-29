<?php

use App\Models\Transaction;

test('stores normalized search terms on save', function () {
    $statement = importedMonth('2026-06');
    $transaction = Transaction::factory()->for($statement)->create([
        'merchant' => 'LOTTO He ssen',
        'counterparty' => 'PayPal Europe',
        'purpose' => 'Ihr Einkauf bei Müller',
    ]);
    $withoutCounterparty = Transaction::factory()->for($statement)->create(['counterparty' => null]);

    expect($transaction->fresh()->search)->toBe(['LOTTOHESSEN', 'PAYPALEUROPE', 'IHREINKAUFBEIMUELLER'])
        ->and($withoutCounterparty->fresh()->search)->toHaveCount(2);
});

test('backfills the search field', function () {
    $collection = Transaction::query()->toBase()->raw();
    $id = $collection->insertOne(['merchant' => 'Takeaway .com', 'counterparty' => null, 'purpose' => ''])->getInsertedId();

    (require database_path('migrations/2026_09_30_100000_backfill_transaction_search.php'))->up();

    expect((array) $collection->findOne(['_id' => $id])['search'])->toBe(['TAKEAWAY.COM']);
});
