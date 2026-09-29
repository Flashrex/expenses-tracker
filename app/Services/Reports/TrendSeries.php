<?php

namespace App\Services\Reports;

use App\Enums\ReportMode;
use App\Support\Period;

/**
 * Spending per group over a continuous range of months or years, ready for a stacked bar chart.
 */
final readonly class TrendSeries
{
    /**
     * @param  list<string>  $periods  'YYYY-MM' (month) or 'YYYY' (year), ascending, continuous
     * @param  list<string>  $labels  axis labels aligned with $periods
     * @param  list<array{key: string, name: string, color: string, values: list<int>}>  $groups  values = positive cents aligned with $periods
     */
    private function __construct(
        public ReportMode $mode,
        public array $periods,
        public array $labels,
        public array $groups,
    ) {}

    /**
     * @param  array<string, Totals>  $totalsByPeriod  SpendingReport::totalsByPeriod() output
     * @param  array<string, array{name: string, color: string, sort: int}>  $groups  config('expenses.groups')
     */
    public static function build(ReportMode $mode, string $firstMonth, string $lastMonth, array $totalsByPeriod, array $groups): self
    {
        $buckets = $mode === ReportMode::Month
            ? self::monthBuckets($firstMonth, $lastMonth, $totalsByPeriod)
            : self::yearBuckets($firstMonth, $lastMonth, $totalsByPeriod);

        $periods = array_map('strval', array_keys($buckets));
        $labels = $mode === ReportMode::Month ? array_map(Period::short(...), $periods) : $periods;

        uasort($groups, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);
        $groups[GroupComparison::UNASSIGNED] = ['name' => 'Unassigned', 'color' => '#cbd5e1'];

        $series = [];

        foreach ($groups as $key => $group) {
            $values = array_values(array_map(fn (Totals $totals) => $totals->groupCents[$key] ?? 0, $buckets));

            if (array_sum($values) > 0) {
                $series[] = ['key' => (string) $key, 'name' => $group['name'], 'color' => $group['color'], 'values' => $values];
            }
        }

        return new self($mode, $periods, $labels, $series);
    }

    /**
     * @return list<int> sum of all group values per period
     */
    public function totals(): array
    {
        return array_map(
            fn (int $i) => array_sum(array_map(fn (array $group) => $group['values'][$i], $this->groups)),
            array_keys($this->periods),
        );
    }

    /**
     * "Jun 2025 – Jun 2026", "2025 – 2026", or a single label.
     */
    public function rangeLabel(): string
    {
        $first = $this->labels[0];
        $last = $this->labels[array_key_last($this->labels)];

        return $first === $last ? $first : "{$first} – {$last}";
    }

    public function hasSpending(): bool
    {
        return $this->groups !== [];
    }

    /**
     * @param  array<string, Totals>  $totalsByPeriod
     * @return array<string, Totals>
     */
    private static function monthBuckets(string $firstMonth, string $lastMonth, array $totalsByPeriod): array
    {
        [$year, $month] = array_map('intval', explode('-', $firstMonth));
        $buckets = [];

        do {
            $period = sprintf('%04d-%02d', $year, $month);
            $buckets[$period] = $totalsByPeriod[$period] ?? Totals::empty();

            if (++$month > 12) {
                $month = 1;
                $year++;
            }
        } while ($period < $lastMonth);

        return $buckets;
    }

    /**
     * @param  array<string, Totals>  $totalsByPeriod
     * @return array<string, Totals>
     */
    private static function yearBuckets(string $firstMonth, string $lastMonth, array $totalsByPeriod): array
    {
        $buckets = [];

        foreach (range((int) substr($firstMonth, 0, 4), (int) substr($lastMonth, 0, 4)) as $year) {
            $inYear = array_filter($totalsByPeriod, fn (string $period) => str_starts_with($period, "{$year}-"), ARRAY_FILTER_USE_KEY);
            $buckets[(string) $year] = $inYear === [] ? Totals::empty() : Totals::sum($inYear);
        }

        return $buckets;
    }
}
