@use('App\Support\Money')

@php
    $state = $match->state($entry->direction());
    $isShared = $match->shareDivisor > 1 && ! $match->ignored;
    $countedCents = (int) round($entry->amountCents / $match->shareDivisor, 0, PHP_ROUND_HALF_EVEN);
    $current = $overrides[$i] ?? $match->groupKey;
    $isOverridden = isset($overrides[$i]);
    $groupOf = "groupOf({$i}, '{$match->groupKey}')";
    $shareChip = 'inline-flex items-center rounded-full border border-slate-200 px-1.5 py-0.5 text-xs font-medium tabular-nums text-slate-600 dark:border-slate-700 dark:text-slate-300';
@endphp

<li data-assignment="{{ $state }}" data-review-row="{{ $i }}" @if ($state === 'group') data-group="{{ $match->groupKey }}" @endif x-data="{ open: false }" @class(['px-4', 'opacity-50' => $state === 'ignored'])>
    <div data-row-toggle @click="togglesRow($event) && (open = ! open)" class="flex cursor-pointer items-start gap-3 py-2.5 text-sm">
        <span class="w-12 shrink-0 tabular-nums text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Carbon::parse($entry->bookedOn)->format('d.m.') }}</span>

        <div class="min-w-0 flex-1">
            <div class="truncate font-medium" title="{{ $entry->merchant }}">{{ $entry->merchant }}</div>

            @if ($state !== 'income')
                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    @if ($overridable)
                        <span class="relative inline-flex max-w-full items-center gap-1.5" x-data="{ picking: false }"
                            @click.outside="picking = false" @keydown.escape.window="if (picking) { picking = false; $refs.chip.focus() }">
                            <button type="button" x-ref="chip" data-group-chip aria-haspopup="true" :aria-expanded="picking" aria-expanded="false"
                                @click="picking = ! picking" :disabled="busy"
                                :class="{ 'border-dashed border-slate-400 dark:border-slate-500': isOverridden({{ $i }}), 'border-transparent': ! isOverridden({{ $i }}) }"
                                @class([
                                    'inline-flex max-w-full items-center gap-1.5 rounded-full border bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:focus-visible:outline-emerald-400',
                                    'border-dashed border-slate-400 dark:border-slate-500' => $isOverridden,
                                    'border-transparent' => ! $isOverridden,
                                ])>
                                <span class="size-2 shrink-0 rounded-full" :style="`background-color: ${groups[{{ $groupOf }}].color}`" style="background-color: {{ config("expenses.groups.$current.color") }}"></span>
                                <span class="truncate" x-text="groups[{{ $groupOf }}].name">{{ config("expenses.groups.$current.name") }}</span>
                                <x-heroicon-m-pencil data-override-marker x-show="isOverridden({{ $i }})" @style(['display: none' => ! $isOverridden]) class="size-3 shrink-0 text-slate-500 dark:text-slate-400" />
                            </button>

                            @if ($match->shareDivisor > 1)
                                <span data-share x-show="! isOverridden({{ $i }})" @style(['display: none' => $isOverridden]) title="Your share: 1/{{ $match->shareDivisor }}" class="{{ $shareChip }}">÷{{ $match->shareDivisor }}</span>
                            @endif

                            <button type="button" data-reset x-show="isOverridden({{ $i }})" @style(['display: none' => ! $isOverridden])
                                @click="override({{ $i }}, '{{ $match->groupKey }}')" :disabled="busy"
                                title="Reset to {{ $ruleGroupName }}" aria-label="Reset to {{ $ruleGroupName }}"
                                class="rounded-full p-0.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200 dark:focus-visible:outline-emerald-400">
                                <x-heroicon-m-arrow-uturn-left class="size-3.5" />
                            </button>

                            <div data-group-picker role="menu" aria-label="Group for {{ $entry->merchant }}" x-show="picking" x-cloak
                                class="absolute top-full left-0 z-20 mt-1 w-64 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                @foreach (config('expenses.groups') as $key => $group)
                                    <button type="button" role="menuitemradio" data-pick-override="{{ $key }}"
                                        :aria-checked="{{ $groupOf }} === '{{ $key }}'" aria-checked="{{ $current === $key ? 'true' : 'false' }}"
                                        @click="picking = false; override({{ $i }}, '{{ $key }}')" :disabled="busy"
                                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-300 dark:hover:bg-slate-800 dark:focus-visible:outline-emerald-400">
                                        <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $group['color'] }}"></span>
                                        <span class="flex-1 truncate">{{ $group['name'] }}</span>
                                        <x-heroicon-m-check x-show="{{ $groupOf }} === '{{ $key }}'" @style(['display: none' => $current !== $key]) class="size-4 text-emerald-600 dark:text-emerald-400" />
                                    </button>
                                @endforeach
                            </div>
                        </span>
                    @elseif ($state === 'group')
                        <x-group-chip :group="$match->groupKey" />

                        @if ($match->shareDivisor > 1)
                            <span data-share title="Your share: 1/{{ $match->shareDivisor }}" class="{{ $shareChip }}">÷{{ $match->shareDivisor }}</span>
                        @endif
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-heroicon-m-eye-slash class="size-3.5" />ignored</span>
                    @endif
                </div>
            @endif
        </div>

        <span @class([
            'whitespace-nowrap text-right font-medium tabular-nums',
            'text-rose-600 dark:text-rose-400' => $entry->amountCents < 0,
            'text-emerald-600 dark:text-emerald-400' => $entry->amountCents >= 0,
            'line-through' => $state === 'ignored',
        ])>{{ Money::format($entry->amountCents, true) }}</span>

        @include('pages.upload-review.entry-toggle')
    </div>

    <x-entry-details id="review-entry-{{ $i }}" :type="$entry->type" :counterparty="$entry->counterparty" :booked-on="$date($entry->bookedOn)" :value-on="$date($entry->valueOn)" :purpose="$entry->purpose">
        <x-slot:amount>@if ($isShared && $overridable)<span x-show="! isOverridden({{ $i }})" @style(['display: none' => $isOverridden])>{{ Money::format($countedCents, true) }} ({{ Money::format($entry->amountCents, true) }} / {{ $match->shareDivisor }})</span><span x-show="isOverridden({{ $i }})" @style(['display: none' => ! $isOverridden])>{{ Money::format($entry->amountCents, true) }}</span>@elseif ($isShared){{ Money::format($countedCents, true) }} ({{ Money::format($entry->amountCents, true) }} / {{ $match->shareDivisor }})@else{{ Money::format($entry->amountCents, true) }}@if ($match->ignored)<span class="text-slate-500 dark:text-slate-400"> · ignored</span>@endif @endif</x-slot:amount>
        <x-slot:group>@if ($overridable)@include('pages.upload-review.bound-chip', ['groupExpression' => $groupOf, 'current' => $current])@elseif ($state === 'group')<x-group-chip :group="$match->groupKey" />@else—@endif</x-slot:group>
        <x-slot:grouped-by>@if ($overridable)<span x-text="isOverridden({{ $i }}) ? 'Picked manually' : @js($ruleText)">{{ $isOverridden ? 'Picked manually' : $ruleText }}</span>@else{{ $ruleText }}@endif</x-slot:grouped-by>
    </x-entry-details>
</li>
