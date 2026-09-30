<?php

namespace App\Models;

use App\Services\Rules\TextNormalizer;
use App\Services\Statements\ParsedEntry;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property list<string> $search
 * @property ?string $description the user's own note on the entry
 * @property array{group_key: ?string, share_divisor: int, ignored: bool}|null $declined the rerun result the user declined for this entry
 */
#[Fillable([
    'statement_id', 'period', 'booked_on', 'value_on', 'type', 'counterparty', 'purpose', 'merchant',
    'amount_cents', 'direction', 'group_key', 'share_divisor', 'ignored', 'rule_id', 'declined', 'description',
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
        'declined' => null,
        'description' => null,
    ];

    /**
     * Normalized merchant, counterparty, purpose and description, so search matches text with stray spaces and umlauts.
     *
     * @return list<string>
     */
    public static function searchTermsFor(?string $merchant, ?string $counterparty, ?string $purpose, ?string $description = null): array
    {
        return array_values(array_filter(array_map(
            TextNormalizer::normalize(...),
            [$merchant, $counterparty, $purpose, $description],
        ), fn (string $term) => $term !== ''));
    }

    protected static function booted(): void
    {
        static::saving(function (Transaction $transaction) {
            $transaction->search = self::searchTermsFor($transaction->merchant, $transaction->counterparty, $transaction->purpose, $transaction->description);
        });
    }

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

    /**
     * The rule that grouped or ignored this entry, if any.
     *
     * @return BelongsTo<Rule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    /**
     * The entry as the rule matcher sees it.
     */
    public function toParsedEntry(): ParsedEntry
    {
        return new ParsedEntry(
            bookedOn: $this->booked_on->format('Y-m-d'),
            valueOn: $this->value_on->format('Y-m-d'),
            type: (string) $this->type,
            counterparty: $this->counterparty,
            purpose: (string) $this->purpose,
            merchant: (string) $this->merchant,
            amountCents: $this->amount_cents,
        );
    }

    /**
     * How the entry is grouped now.
     *
     * @return array{group_key: ?string, share_divisor: int, ignored: bool}
     */
    public function result(): array
    {
        return [
            'group_key' => $this->group_key,
            'share_divisor' => $this->share_divisor,
            'ignored' => $this->ignored,
        ];
    }
}
