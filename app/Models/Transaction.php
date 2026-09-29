<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

#[Fillable([
    'statement_id', 'period', 'booked_on', 'value_on', 'type', 'counterparty', 'purpose', 'merchant',
    'amount_cents', 'direction', 'group_key', 'share_divisor', 'ignored', 'rule_id',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'group_key' => null,
        'share_divisor' => 1,
        'ignored' => false,
        'rule_id' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booked_on' => 'date',
            'value_on' => 'date',
            'amount_cents' => 'integer',
            'share_divisor' => 'integer',
            'ignored' => 'boolean',
        ];
    }

    /**
     * The statement this entry was imported from.
     *
     * @return BelongsTo<Statement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class);
    }
}
