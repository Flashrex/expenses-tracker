<?php

namespace App\Services\Reports;

use App\Models\Transaction;

/**
 * One entry of the entries card, with everything its row and details show.
 */
final readonly class EntryRow
{
    public function __construct(
        public string $id,
        public string $period,
        public string $bookedShort,
        public string $bookedOn,
        public string $valueOn,
        public string $type,
        public ?string $counterparty,
        public string $purpose,
        public string $merchant,
        public int $amountCents,
        public int $shareDivisor,
        public int $countedCents,
        public ?string $groupKey,
        public bool $ignored,
        public string $groupedBy,
    ) {}

    /**
     * Uses the loaded `rule` relation to explain how the entry was grouped.
     */
    public static function fromTransaction(Transaction $transaction): self
    {
        $rule = $transaction->rule;

        $groupedBy = match (true) {
            $transaction->rule_id !== null && $rule !== null => $rule->description(),
            $transaction->rule_id !== null => 'Rule (since removed)',
            $transaction->group_key !== null || $transaction->ignored || $transaction->declined !== null => 'Picked manually',
            default => 'Not grouped',
        };

        return new self(
            id: (string) $transaction->id,
            period: $transaction->period,
            bookedShort: $transaction->booked_on->format('d.m.'),
            bookedOn: $transaction->booked_on->format('d.m.Y'),
            valueOn: $transaction->value_on->format('d.m.Y'),
            type: (string) $transaction->type,
            counterparty: $transaction->counterparty === '' ? null : $transaction->counterparty,
            purpose: (string) $transaction->purpose,
            merchant: (string) $transaction->merchant,
            amountCents: $transaction->amount_cents,
            shareDivisor: $transaction->share_divisor,
            countedCents: (int) round($transaction->amount_cents / $transaction->share_divisor, 0, PHP_ROUND_HALF_EVEN),
            groupKey: $transaction->group_key,
            ignored: $transaction->ignored,
            groupedBy: $groupedBy,
        );
    }

    public function isShared(): bool
    {
        return $this->shareDivisor > 1 && ! $this->ignored;
    }

    public function isUnassigned(): bool
    {
        return $this->amountCents < 0 && ! $this->ignored && $this->groupKey === null;
    }
}
