<?php

namespace App\Enums;

enum RuleDirection: string
{
    case In = 'in';
    case Out = 'out';
    case Any = 'any';

    /**
     * Whether a rule with this direction applies to an entry going "in" or "out".
     */
    public function matches(string $entryDirection): bool
    {
        return $this === self::Any || $this->value === $entryDirection;
    }
}
