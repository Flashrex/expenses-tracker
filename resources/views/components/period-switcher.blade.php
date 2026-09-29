@props(['period', 'earlier', 'later', 'toggled', 'route' => 'overview'])

@php
    $arrow = 'rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400';
    $disabled = 'rounded-lg p-2 text-slate-300 dark:text-slate-700';
@endphp

<div class="flex flex-wrap items-center justify-between gap-3">
    <x-mode-toggle :mode="$period->mode" :month-url="route($route, $toggled->query())" :year-url="route($route, $toggled->query())" />

    <div class="flex items-center gap-1">
        @if ($earlier)
            <a data-nav="prev" href="{{ route($route, $earlier->query()) }}" aria-label="Previous: {{ $earlier->label() }}" title="{{ $earlier->label() }}" class="{{ $arrow }}">
                <x-heroicon-o-chevron-left class="size-5" />
            </a>
        @else
            <span data-nav="prev" aria-disabled="true" class="{{ $disabled }}"><x-heroicon-o-chevron-left class="size-5" /></span>
        @endif

        <h1 class="min-w-36 text-center text-lg font-semibold tabular-nums">{{ $period->label() }}</h1>

        @if ($later)
            <a data-nav="next" href="{{ route($route, $later->query()) }}" aria-label="Next: {{ $later->label() }}" title="{{ $later->label() }}" class="{{ $arrow }}">
                <x-heroicon-o-chevron-right class="size-5" />
            </a>
        @else
            <span data-nav="next" aria-disabled="true" class="{{ $disabled }}"><x-heroicon-o-chevron-right class="size-5" /></span>
        @endif
    </div>
</div>
