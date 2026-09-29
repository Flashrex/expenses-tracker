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

            @if ($series->hasSpending())
                <div x-data="trendChart" data-chart="{{ json_encode($chart) }}" class="space-y-6">
                    <section aria-labelledby="trend-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 id="trend-heading" class="text-sm font-semibold">Spending by group</h2>

                            <button type="button" data-lock-chip x-show="locked" x-cloak x-on:click="unlock()"
                                    x-bind:aria-label="locked ? `Unlock ${locked.name}` : null" x-bind:title="locked ? `Unlock ${locked.name}` : null"
                                    class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:focus-visible:outline-emerald-400">
                                <x-heroicon-m-x-mark class="size-3.5 shrink-0" />
                                <span class="size-2 shrink-0 rounded-full" x-bind:style="{ backgroundColor: locked?.color }"></span>
                                <span class="truncate" x-text="locked?.name"></span>
                            </button>
                        </div>

                        <div x-ref="chart" role="img" aria-label="Spending by group per {{ $mode->value }}, {{ $series->rangeLabel() }}" class="mt-4 h-80 w-full sm:h-96"></div>

                        @if ($singleMonth)
                            <p data-single-period class="mt-3 text-xs text-slate-500 dark:text-slate-400">Import another month to compare trends.</p>
                        @endif
                    </section>

                    <section aria-labelledby="trend-line-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                        <h2 id="trend-line-heading" class="text-sm font-semibold" x-text="locked ? `${locked.name} over time` : 'Total spending over time'">Total spending over time</h2>
                        <p data-lock-hint x-show="!locked" class="mt-1 text-xs text-slate-500 dark:text-slate-400">Click a group in the chart above to see it here</p>

                        <div x-ref="line" role="img" aria-label="Spending over time per {{ $mode->value }}, {{ $series->rangeLabel() }}" class="mt-4 h-64 w-full sm:h-72"></div>

                        @if ($singleMonth)
                            <p data-single-period class="mt-3 text-xs text-slate-500 dark:text-slate-400">Import another month to compare trends.</p>
                        @endif
                    </section>
                </div>
            @else
                <section aria-labelledby="trend-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                    <h2 id="trend-heading" class="text-sm font-semibold">Spending by group</h2>

                    <p data-no-spending class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No spending in {{ $series->rangeLabel() }}</p>

                    @if ($singleMonth)
                        <p data-single-period class="mt-3 text-xs text-slate-500 dark:text-slate-400">Import another month to compare trends.</p>
                    @endif
                </section>

                <section aria-labelledby="trend-line-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                    <h2 id="trend-line-heading" class="text-sm font-semibold">Total spending over time</h2>

                    <p data-no-spending class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No spending in {{ $series->rangeLabel() }}</p>

                    @if ($singleMonth)
                        <p data-single-period class="mt-3 text-xs text-slate-500 dark:text-slate-400">Import another month to compare trends.</p>
                    @endif
                </section>
            @endif
        </div>
    @endif
</x-layouts.app>
