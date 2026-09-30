<?php

namespace App\Http\Controllers;

use App\Models\Statement;
use App\Services\Groups\GroupCatalog;
use App\Services\Reports\EntryFilters;
use App\Services\Reports\EntryList;
use App\Services\Reports\ReportPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OverviewEntriesController extends Controller
{
    /**
     * Render only the body of the Overview's entries card, for swapping it in place.
     */
    public function __invoke(Request $request, EntryList $entryList, GroupCatalog $groups): View|RedirectResponse
    {
        $imported = Statement::importedPeriods();

        abort_if($imported === [], 404);

        $period = ReportPeriod::resolve($request->query('month'), $request->query('year'), $imported);

        abort_if($period === null, 404);

        $filters = EntryFilters::fromQuery($request->query(), $groups->keys());

        if ($filters === null) {
            return redirect()->route('overview.entries', $period->query());
        }

        $entries = $entryList->page($period, $filters);

        if ($filters->page > $entries->lastPage()) {
            return redirect()->route('overview.entries', $period->query() + $filters->withPage($entries->lastPage())->query());
        }

        return view('pages.overview.entries-body', compact('period', 'filters', 'entries'));
    }
}
