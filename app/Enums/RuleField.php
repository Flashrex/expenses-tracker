<?php

namespace App\Enums;

use App\Services\Statements\ParsedEntry;

enum RuleField: string
{
    case Merchant = 'merchant';
    case Counterparty = 'counterparty';
    case Purpose = 'purpose';
    case Type = 'type';

    /**
     * The text of the entry this field points at.
     */
    public function valueOf(ParsedEntry $entry): string
    {
        return match ($this) {
            self::Merchant => $entry->merchant,
            self::Counterparty => $entry->counterparty ?? '',
            self::Purpose => $entry->purpose,
            self::Type => $entry->type,
        };
    }
}
