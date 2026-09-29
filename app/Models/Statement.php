<?php

namespace App\Models;

use Database\Factories\StatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

#[Fillable(['number', 'period', 'statement_date', 'old_balance_cents', 'new_balance_cents', 'confirmed_at'])]
class Statement extends Model
{
    /** @use HasFactory<StatementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'statement_date' => 'date',
            'old_balance_cents' => 'integer',
            'new_balance_cents' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Periods (YYYY-MM) that have a confirmed statement, ascending and unique.
     *
     * @return list<string>
     */
    public static function importedPeriods(): array
    {
        return static::query()->whereNotNull('confirmed_at')->pluck('period')->unique()->sort()->values()->all();
    }

    /**
     * The entries booked on this statement.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
