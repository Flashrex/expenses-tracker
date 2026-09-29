<?php

namespace App\Http\Controllers;

use App\Enums\ReportMode;
use App\Models\Statement;
use App\Services\Reports\SpendingReport;
use App\Services\Reports\TrendSeries;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrendsController extends Controller
{
    /**
     * Show spending per group for every month (or year) from the first to the last import.
     */
    public function __invoke(Request $request, SpendingReport $report): View|RedirectResponse
    {
        $imported = Statement::importedPeriods();

        if ($imported === []) {
            return view('pages.trends', ['series' => null]);
        }

        $by = $request->query('by');

        if ($by !== null && $by !== 'year') {
            return redirect()->route('trends');
        }

        $mode = $by === 'year' ? ReportMode::Year : ReportMode::Month;

        $first = $imported[0];
        $last = $imported[array_key_last($imported)];

        $series = TrendSeries::build($mode, $imported, $report->totalsByPeriod($first, $last), config('expenses.groups'));

        $chart = ['labels' => $series->labels, 'imported' => $series->imported, 'series' => $series->groups];

        return view('pages.trends', [
            'series' => $series,
            'chart' => $chart,
            'mode' => $mode,
            'singleMonth' => count($imported) === 1,
        ]);
    }
}
