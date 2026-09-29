<?php

use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $collection = Transaction::query()->toBase()->raw();

        foreach ($collection->find(['search' => ['$exists' => false]], ['projection' => ['merchant' => 1, 'counterparty' => 1, 'purpose' => 1]]) as $document) {
            $collection->updateOne(['_id' => $document['_id']], ['$set' => [
                'search' => Transaction::searchTermsFor($document['merchant'] ?? null, $document['counterparty'] ?? null, $document['purpose'] ?? null),
            ]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Transaction::query()->toBase()->raw()->updateMany([], ['$unset' => ['search' => '']]);
    }
};
