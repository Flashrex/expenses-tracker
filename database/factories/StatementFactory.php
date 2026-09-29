<?php

namespace Database\Factories;

use App\Models\Statement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Statement>
 */
class StatementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 6,
            'period' => '2026-06',
            'statement_date' => '2026-06-30',
            'old_balance_cents' => 227202,
            'new_balance_cents' => 195230,
            'confirmed_at' => now(),
        ];
    }
}
