<?php

namespace App\Enums;

enum RuleOperator: string
{
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case GreaterThan = 'greater_than';
    case AtLeast = 'at_least';
    case LessThan = 'less_than';
    case AtMost = 'at_most';

    public function label(): string
    {
        return match ($this) {
            self::Contains => 'contains',
            self::NotContains => "doesn't contain",
            self::Equals => 'is equal to',
            self::NotEquals => 'is not equal to',
            self::GreaterThan => 'is greater than',
            self::AtLeast => 'is at least',
            self::LessThan => 'is less than',
            self::AtMost => 'is at most',
        };
    }

    /**
     * The operators a field offers, the first one being the default of new condition rows.
     *
     * @return list<self>
     */
    public static function forField(RuleField $field): array
    {
        return $field->isAmount()
            ? [self::Equals, self::NotEquals, self::GreaterThan, self::AtLeast, self::LessThan, self::AtMost]
            : [self::Contains, self::NotContains, self::Equals];
    }
}
