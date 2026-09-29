<?php

namespace App\Http\Controllers;

use App\Models\Statement;
use App\Services\Reports\EntryFilters;
use App\Services\Reports\EntryList;
use App\Services\Reports\GroupComparison;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SpendingReport;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    /**
     * Show spending by group for one imported month or year, compared with the previous period, and its entries.
     */
    public function __invoke(Request $request, SpendingReport $report, EntryList $entryList): View|RedirectResponse
    {
        $imported = Statement::importedPeriods();

        if ($imported === []) {
            return view('pages.overview', ['period' => null]);
        }

        $period = ReportPeriod::resolve($request->query('month'), $request->query('year'), $imported);

        if ($period === null) {
            return redirect()->route('overview');
        }

        $filters = EntryFilters::fromQuery($request->query(), array_keys(config('expenses.groups')));

        if ($filters === null) {
            return redirect()->route('overview', $period->query());
        }

        $entries = $entryList->page($period, $filters);

        if ($filters->page > $entries->lastPage()) {
            return redirect()->route('overview', $period->query() + $filters->withPage($entries->lastPage())->query());
        }

        $previous = $period->previous();
        $hasPrevious = $previous->hasData($imported);

        $totals = $report->totals($period->from(), $period->to());
        $previousTotals = $hasPrevious ? $report->totals($previous->from(), $previous->to()) : null;

        $rows = GroupComparison::rows($totals, $previousTotals, config('expenses.groups'));

        $chart = [
            'total' => Money::format($totals->spentCents),
            'label' => 'Spent',
            'slices' => collect($rows)
                ->filter(fn (GroupComparison $row) => $row->cents > 0)
                ->map(fn (GroupComparison $row) => [
                    'key' => $row->key,
                    'name' => $row->name,
                    'value' => $row->cents,
                    'color' => $row->color,
                    'amount' => Money::format($row->cents),
                ])
                ->values()
                ->all(),
        ];

        return view('pages.overview', compact('period', 'previous', 'hasPrevious', 'totals', 'rows', 'chart', 'filters', 'entries') + [
            'earlier' => $period->earlier($imported),
            'later' => $period->later($imported),
            'toggled' => $period->toggled($imported),
        ]);
    }
}
