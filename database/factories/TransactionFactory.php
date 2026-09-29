<?php

namespace Database\Factories;

use App\Models\Statement;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statement_id' => Statement::factory(),
            'period' => '2026-06',
            'booked_on' => '2026-06-01',
            'value_on' => '2026-06-01',
            'type' => 'Lastschrift',
            'counterparty' => 'VISA TEGUT FILIALE 5020',
            'purpose' => 'NR XXXX 9533 FULDA DE KAUFUMSATZ',
            'merchant' => 'TEGUT',
            'amount_cents' => -399,
            'direction' => 'out',
        ];
    }
}
