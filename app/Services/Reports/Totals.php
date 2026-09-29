<?php

namespace App\Services\Reports;

final readonly class Totals
{
    /**
     * @param  array<string, int>  $groupCents  group key (or 'unassigned') => counted spending, positive cents, only non-zero
     */
    public function __construct(public int $spentCents, public int $incomeCents, public array $groupCents) {}

    public static function empty(): self
    {
        return new self(0, 0, []);
    }

    /**
     * @param  iterable<Totals>  $totals
     */
    public static function sum(iterable $totals): self
    {
        $spent = 0;
        $income = 0;
        $groups = [];

        foreach ($totals as $total) {
            $spent += $total->spentCents;
            $income += $total->incomeCents;

            foreach ($total->groupCents as $key => $cents) {
                $groups[$key] = ($groups[$key] ?? 0) + $cents;
            }
        }

        return new self($spent, $income, array_filter($groups));
    }

    public function netCents(): int
    {
        return $this->incomeCents - $this->spentCents;
    }
}
