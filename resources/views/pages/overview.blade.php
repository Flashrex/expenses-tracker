@use('App\Support\Money')
@use('App\Support\Percent')

<x-layouts.app title="Overview">
    @if ($period === null)
        <x-empty-state
            icon="heroicon-o-chart-pie"
            title="No data yet"
            text="Upload your first bank statement to see where your money goes."
        >
            <x-slot:action>
                <a href="{{ route('upload') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400">
                    <x-heroicon-o-arrow-up-tray class="size-5" /> Upload statement
                </a>
            </x-slot:action>
        </x-empty-state>
    @else
        @php
            $netTone = match ($totals->netCents() <=> 0) {
                1 => 'positive',
                -1 => 'negative',
                0 => 'neutral',
            };
        @endphp

        <div class="space-y-6">
            <x-period-switcher :period="$period" :earlier="$earlier" :later="$later" :toggled="$toggled" />

            <div class="grid gap-4 sm:grid-cols-3">
                <x-kpi-tile label="Spent" icon="heroicon-o-arrow-up-right" :value="Money::format($totals->spentCents)" />
                <x-kpi-tile label="Income" icon="heroicon-o-arrow-down-left" :value="Money::format($totals->incomeCents)" />
                <x-kpi-tile
                    label="Net"
                    icon="heroicon-o-scale"
                    :value="Money::format($totals->netCents(), true)"
                    :tone="$netTone"
                />
            </div>

            <section aria-labelledby="groups-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="groups-heading" class="text-sm font-semibold">By group</h2>
                    @if ($hasPrevious)
                        <p class="text-xs text-slate-500 dark:text-slate-400">vs {{ $previous->label() }}</p>
                    @endif
                </div>

                <div class="mt-4 grid items-center gap-6 lg:grid-cols-[18rem_1fr]">
                    @if ($totals->spentCents > 0)
                        <div x-data="donutChart" data-chart="{{ json_encode($chart) }}" class="mx-auto w-full max-w-72">
                            <div x-ref="chart" role="img" aria-label="Spending by group, total {{ $chart['total'] }}" class="aspect-square w-full"></div>
                        </div>
                    @else
                        <p data-no-spending class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No spending in {{ $period->label() }}</p>
                    @endif

                    <div>
                        @if ($rows !== [])
                            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($rows as $row)
                                    <li data-group-row="{{ $row->key }}" @if ($row->trend) data-trend="{{ $row->trend }}" @endif class="flex items-center gap-3 py-2.5">
                                        <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $row->color }}"></span>
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium" title="{{ $row->name }}">{{ $row->name }}</span>
                                        <span class="hidden text-xs tabular-nums text-slate-500 sm:inline dark:text-slate-400">{{ Percent::format($row->share) }}</span>
                                        <span class="whitespace-nowrap text-sm font-semibold tabular-nums">{{ Money::format($row->cents) }}</span>
                                        @if ($row->trend !== null)
                                            <span data-delta @class([
                                                'w-16 shrink-0 text-right text-xs font-semibold tabular-nums',
                                                'text-rose-600 dark:text-rose-400' => in_array($row->trend, ['up', 'new'], true),
                                                'text-emerald-600 dark:text-emerald-400' => $row->trend === 'down',
                                                'text-slate-500 dark:text-slate-400' => $row->trend === 'same',
                                            ])>
                                                {{ match ($row->trend) {
                                                    'up' => '↑ '.Percent::format($row->deltaPercent, 0),
                                                    'down' => '↓ '.Percent::format($row->deltaPercent, 0),
                                                    'same' => '±0 %',
                                                    'new' => 'new',
                                                } }}
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @unless ($hasPrevious)
                            <p data-no-comparison class="mt-3 text-xs text-slate-500 dark:text-slate-400">No data for {{ $previous->label() }} to compare</p>
                        @endunless
                    </div>
                </div>
            </section>
        </div>
    @endif
</x-layouts.app>
