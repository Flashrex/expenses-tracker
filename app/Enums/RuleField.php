<?php

namespace App\Enums;

use App\Services\Statements\ParsedEntry;

enum RuleField: string
{
    case Merchant = 'merchant';
    case Counterparty = 'counterparty';
    case Purpose = 'purpose';
    case Type = 'type';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Merchant => 'Merchant',
            self::Counterparty => 'Counterparty',
            self::Purpose => 'Purpose',
            self::Type => 'Type',
            self::Amount => 'Amount',
        };
    }

    public function isAmount(): bool
    {
        return $this === self::Amount;
    }

    /**
     * The text of the entry this field points at; the amount without its sign, in cents.
     */
    public function valueOf(ParsedEntry $entry): string
    {
        return match ($this) {
            self::Merchant => $entry->merchant,
            self::Counterparty => $entry->counterparty ?? '',
            self::Purpose => $entry->purpose,
            self::Type => $entry->type,
            self::Amount => (string) abs($entry->amountCents),
        };
    }
}
