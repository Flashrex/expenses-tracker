<x-layouts.app title="Trends">
    @if ($series === null)
        <x-empty-state
            icon="heroicon-o-chart-bar"
            title="No trends yet"
            text="Trends appear once you have imported a statement."
        >
            <x-slot:action>
                <a href="{{ route('upload') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400">
                    <x-heroicon-o-arrow-up-tray class="size-5" /> Upload statement
                </a>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-mode-toggle :mode="$mode" :month-url="route('trends')" :year-url="route('trends', ['by' => 'year'])" />

                <h1 class="text-lg font-semibold tabular-nums">{{ $series->rangeLabel() }}</h1>
            </div>

            <section aria-labelledby="trend-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 id="trend-heading" class="text-sm font-semibold">Spending by group</h2>

                @if ($series->hasSpending())
                    <div x-data="trendChart" data-chart="{{ json_encode($chart) }}" class="mt-4">
                        <div x-ref="chart" role="img" aria-label="Spending by group per {{ $mode->value }}, {{ $series->rangeLabel() }}" class="h-80 w-full sm:h-96"></div>
                    </div>
                @else
                    <p data-no-spending class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No spending in {{ $series->rangeLabel() }}</p>
                @endif

                @if ($singleMonth)
                    <p data-single-period class="mt-3 text-xs text-slate-500 dark:text-slate-400">Import another month to compare trends.</p>
                @endif
            </section>
        </div>
    @endif
</x-layouts.app>
