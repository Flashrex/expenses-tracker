@use('App\Enums\EntryStatus')
@use('App\Services\Reports\GroupComparison')
@use('App\Support\Period')

@php
    $link = fn (App\Services\Reports\EntryFilters $to) => route('overview', $period->query() + $to->query()).'#entries';
    $segment = 'rounded-md px-3 py-1.5 text-sm font-medium';
    $chip = 'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400';
    $chipSelected = 'border-(--group) bg-(--group)/15 ring-1 ring-(--group) text-slate-900 dark:text-slate-100';
    $chipUnselected = 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600';
    $groups = app(App\Services\Groups\GroupCatalog::class)->all()
        + [GroupComparison::UNASSIGNED => ['name' => 'Unassigned', 'color' => '#cbd5e1']];
    $number = fn (int $n) => number_format($n, 0, ',', '.');
@endphp

<div data-entries-fragment>
    <div class="mt-4 inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900" role="group" aria-label="Status">
        @foreach (EntryStatus::cases() as $case)
            @if ($case === $filters->status)
                <span data-status="{{ $case->value }}" aria-current="true" class="{{ $segment }} bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400">{{ $case->label() }}</span>
            @else
                <a data-status="{{ $case->value }}" data-entries-link href="{{ $link($filters->withStatus($case)) }}" class="{{ $segment }} text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400">{{ $case->label() }}</a>
            @endif
        @endforeach
    </div>

    <div role="group" aria-label="Group" class="-mx-4 mt-3 flex gap-1.5 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0">
        @foreach ($groups as $key => $group)
            @php($active = $filters->group === $key)
            <a data-group-filter="{{ $key }}" data-entries-link
                href="{{ $link($filters->withGroup($active ? null : $key)) }}"
                @if ($active) aria-current="true" @endif
                style="--group: {{ $group['color'] }}"
                @class([$chip, $chipSelected => $active, $chipUnselected => ! $active])><span class="size-2 shrink-0 rounded-full bg-(--group)"></span>{{ $group['name'] }}</a>
        @endforeach
    </div>

    @if ($entries->total > 0)
        <p data-entries-count class="mt-4 text-xs text-slate-500 dark:text-slate-400">{{ $number($entries->from()) }}–{{ $number($entries->to()) }} of {{ $number($entries->total) }}</p>

        <div class="mt-2">
            @if ($period->isMonth())
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($entries->rows as $row)
                        @include('pages.overview.entry-row', ['row' => $row])
                    @endforeach
                </ul>
            @else
                @foreach (collect($entries->rows)->groupBy('period') as $month => $rows)
                    <div data-month="{{ $month }}">
                        <h3 data-month-heading class="sticky top-16 z-1 -mx-4 border-b border-slate-100 bg-white/95 px-4 py-1.5 text-xs font-semibold text-slate-500 backdrop-blur sm:-mx-6 sm:px-6 dark:border-slate-800 dark:bg-slate-900/95 dark:text-slate-400">{{ Period::label($month) }}</h3>
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($rows as $row)
                                @include('pages.overview.entry-row', ['row' => $row])
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endif
        </div>
    @else
        <div data-entries-empty class="py-12 text-center">
            @if ($filters->isFiltered())
                <p class="text-sm text-slate-500 dark:text-slate-400">No entries match these filters</p>
                <a data-entries-link data-clear-filters href="{{ route('overview', $period->query()) }}#entries" class="mt-2 inline-block text-sm font-semibold text-emerald-700 hover:underline dark:text-emerald-400">Clear filters</a>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400">No entries in {{ $period->label() }}</p>
            @endif
        </div>
    @endif

    @if ($entries->lastPage() > 1)
        @include('pages.overview.entries-pagination')
    @endif
</div>
