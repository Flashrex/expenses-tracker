<?php

namespace App\Enums;

enum RuleSource: string
{
    /** A rule of a group card or the Ignored card on the Groups & rules page. */
    case System = 'system';

    /** An "Always use" rule created on the Review page. */
    case Manual = 'manual';
}
