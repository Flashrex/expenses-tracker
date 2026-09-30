@use('App\Support\Money')

@php
    $tone = $row->amountCents < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400';
    $hasChips = $row->groupKey !== null || $row->ignored || $row->isUnassigned() || $row->isShared();
@endphp

<li data-entry="{{ $row->id }}" x-data="{ open: false }" @class(['opacity-50' => $row->ignored])>
    <button type="button" @click="open = ! open" :aria-expanded="open" aria-expanded="false" aria-controls="entry-{{ $row->id }}"
        class="flex w-full items-start gap-3 py-2.5 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:hover:bg-slate-800/60 dark:focus-visible:outline-emerald-400">
        <span class="w-12 shrink-0 text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $row->bookedShort }}</span>

        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium" title="{{ $row->merchant }}">{{ $row->merchant }}</span>
            @if ($hasChips)
                <span class="mt-1 flex flex-wrap items-center gap-1.5">
                    @if ($row->groupKey !== null)
                        <x-group-chip :group="$row->groupKey" />
                    @endif
                    @if ($row->ignored)
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-heroicon-m-eye-slash class="size-3.5" />ignored</span>
                    @endif
                    @if ($row->isUnassigned())
                        <span data-unassigned class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">Unassigned</span>
                    @endif
                    @if ($row->isShared())
                        <span title="Your share: 1/{{ $row->shareDivisor }}" class="inline-flex items-center rounded-full border border-slate-200 px-1.5 py-0.5 text-xs font-medium tabular-nums text-slate-600 dark:border-slate-700 dark:text-slate-300">÷{{ $row->shareDivisor }}</span>
                        <span data-full-amount class="text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ Money::format($row->amountCents, true) }}</span>
                    @endif
                </span>
            @endif
        </span>

        <span data-amount @class(['whitespace-nowrap text-right text-sm font-medium tabular-nums', $tone, 'line-through' => $row->ignored])>{{ Money::format($row->isShared() ? $row->countedCents : $row->amountCents, true) }}</span>

        <span class="mt-0.5 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'"><x-heroicon-m-chevron-down class="size-4" /></span>
    </button>

    <x-entry-details id="entry-{{ $row->id }}" :type="$row->type" :counterparty="$row->counterparty" :booked-on="$row->bookedOn" :value-on="$row->valueOn" :purpose="$row->purpose">
        <x-slot:amount>
            @if ($row->isShared())
                {{ Money::format($row->countedCents, true) }} ({{ Money::format($row->amountCents, true) }} / {{ $row->shareDivisor }})
            @else
                {{ Money::format($row->amountCents, true) }}@if ($row->ignored)<span class="text-slate-500 dark:text-slate-400"> · ignored</span>@endif
            @endif
        </x-slot:amount>
        <x-slot:group>
            @if ($row->groupKey !== null)
                <x-group-chip :group="$row->groupKey" />
            @elseif ($row->isUnassigned())
                Unassigned
            @else
                —
            @endif
        </x-slot:group>
        <x-slot:grouped-by>{{ $row->groupedBy }}</x-slot:grouped-by>
    </x-entry-details>
</li>
