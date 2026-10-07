<?php

namespace App\Services\Reports;

/**
 * One group's spending in a period, compared with the previous period.
 */
final readonly class GroupComparison
{
    public function __construct(
        public string $key,
        public string $name,
        public string $color,
        public int $cents,
        public ?int $previousCents,
        public float $share,
        public ?string $trend,
        public ?int $deltaPercent,
    ) {}

    /**
     * Rows for every group spent on now or in the previous period, largest first.
     *
     * @param  array<string, array{name: string, color: string, sort: int}>  $groups  GroupCatalog::all()
     * @return list<self>
     */
    public static function rows(Totals $current, ?Totals $previous, array $groups): array
    {
        $rows = [];

        foreach ($groups as $key => $group) {
            $cents = $current->groupCents[$key] ?? 0;
            $previousCents = $previous === null ? null : ($previous->groupCents[$key] ?? 0);

            if ($cents <= 0 && $previousCents <= 0) {
                continue;
            }

            [$trend, $deltaPercent] = match (true) {
                $previousCents === null => [null, null],
                $previousCents === 0 => ['new', null],
                default => [
                    match ($cents <=> $previousCents) {
                        1 => 'up',
                        -1 => 'down',
                        0 => 'same',
                    },
                    (int) round(abs($cents - $previousCents) / $previousCents * 100),
                ],
            };

            $rows[] = [
                'sort' => $group['sort'],
                'row' => new self(
                    key: $key,
                    name: $group['name'],
                    color: $group['color'],
                    cents: $cents,
                    previousCents: $previousCents,
                    share: $current->spentCents > 0 ? $cents / $current->spentCents * 100 : 0.0,
                    trend: $trend,
                    deltaPercent: $deltaPercent,
                ),
            ];
        }

        usort($rows, fn (array $a, array $b) => [$b['row']->cents, $b['row']->previousCents ?? 0, $a['sort']]
            <=> [$a['row']->cents, $a['row']->previousCents ?? 0, $b['sort']]);

        return array_column($rows, 'row');
    }
}
