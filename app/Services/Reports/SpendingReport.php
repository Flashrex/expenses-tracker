<?php

namespace App\Services\Reports;

use App\Models\Group;
use App\Models\Transaction;

/**
 * Spending and income per period, aggregated in MongoDB.
 *
 * Every counted amount is amount_cents / share_divisor, rounded to whole cents per entry
 * before summing, so group amounts, the donut total and Spent always add up exactly.
 * Ignored entries are excluded from every total; outgoing entries without a group count as "Other".
 */
final class SpendingReport
{
    /**
     * @return array<string, Totals> period (YYYY-MM) => totals; ascending; only periods that have counted transactions
     */
    public function totalsByPeriod(string $fromPeriod, string $toPeriod): array
    {
        $cursor = Transaction::query()->toBase()->raw()->aggregate([
            ['$match' => [
                'period' => ['$gte' => $fromPeriod, '$lte' => $toPeriod],
                'ignored' => ['$ne' => true],
            ]],
            ['$group' => [
                '_id' => ['period' => '$period', 'direction' => '$direction', 'group_key' => '$group_key'],
                'cents' => ['$sum' => ['$round' => [
                    ['$divide' => ['$amount_cents', ['$ifNull' => ['$share_divisor', 1]]]],
                    0,
                ]]],
            ]],
            ['$sort' => ['_id.period' => 1]],
        ], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);

        $periods = [];

        foreach ($cursor as $row) {
            $period = $row['_id']['period'];
            $cents = (int) round($row['cents']);
            $periods[$period] ??= ['spent' => 0, 'income' => 0, 'groups' => []];

            if ($row['_id']['direction'] === 'out') {
                $key = $row['_id']['group_key'] ?? Group::OTHER;
                $periods[$period]['spent'] -= $cents;
                $periods[$period]['groups'][$key] = ($periods[$period]['groups'][$key] ?? 0) - $cents;
            } elseif ($row['_id']['direction'] === 'in') {
                $periods[$period]['income'] += $cents;
            }
        }

        ksort($periods);

        return array_map(
            fn (array $totals) => new Totals($totals['spent'], $totals['income'], array_filter($totals['groups'])),
            $periods,
        );
    }

    public function totals(string $fromPeriod, string $toPeriod): Totals
    {
        return Totals::sum($this->totalsByPeriod($fromPeriod, $toPeriod));
    }
}
