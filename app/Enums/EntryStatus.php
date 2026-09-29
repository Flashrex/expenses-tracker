<?php

namespace App\Enums;

enum EntryStatus: string
{
    case All = 'all';
    case Spending = 'spending';
    case Income = 'income';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Spending => 'Spending',
            self::Income => 'Income',
            self::Ignored => 'Ignored',
        };
    }
}
