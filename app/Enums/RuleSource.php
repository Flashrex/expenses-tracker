<?php

namespace App\Enums;

enum RuleSource: string
{
    case Seeded = 'seeded';
    case Manual = 'manual';
}
