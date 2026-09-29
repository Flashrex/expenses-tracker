<?php

namespace App\Services\Reports;

use App\Enums\EntryStatus;
use App\Models\Transaction;
use MongoDB\BSON\Regex;

/**
 * The individual entries of a period, newest first, one page at a time.
 *
 * Filters only narrow this list; they never affect the totals of SpendingReport.
 */
final class EntryList
{
    public function page(ReportPeriod $period, EntryFilters $filters): EntryPage
    {
        $query = Transaction::query()
            ->where('period', '>=', $period->from())
            ->where('period', '<=', $period->to());

        if ($filters->group === GroupComparison::UNASSIGNED) {
            $query->where('direction', 'out')->where('ignored', '!=', true)->whereNull('group_key');
        } elseif ($filters->group !== null) {
            $query->where('group_key', $filters->group);
        }

        match ($filters->status) {
            EntryStatus::Spending => $query->where('direction', 'out')->where('ignored', '!=', true),
            EntryStatus::Income => $query->where('direction', 'in')->where('ignored', '!=', true),
            EntryStatus::Ignored => $query->where('ignored', true),
            EntryStatus::All => null,
        };

        if ($filters->search !== null) {
            $query->where('search', 'regex', new Regex(preg_quote($filters->normalizedSearch()), ''));
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('period', 'desc')
            ->orderBy('booked_on', 'desc')
            ->orderBy('_id', 'desc')
            ->skip(($filters->page - 1) * EntryPage::PER_PAGE)
            ->take(EntryPage::PER_PAGE)
            ->with('rule')
            ->get()
            ->map(EntryRow::fromTransaction(...))
            ->values()
            ->all();

        return new EntryPage($rows, $total, $filters->page);
    }
}
