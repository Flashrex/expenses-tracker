@props(['mode', 'monthUrl', 'yearUrl'])

@php
    $segment = 'rounded-md px-3 py-1.5 text-sm font-medium';
@endphp

<div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900" role="group" aria-label="Period type">
    @foreach (App\Enums\ReportMode::cases() as $case)
        @if ($case === $mode)
            <span data-mode="{{ $case->value }}" aria-current="true" class="{{ $segment }} bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400">{{ $case->name }}</span>
        @else
            <a data-mode="{{ $case->value }}" href="{{ $case === App\Enums\ReportMode::Month ? $monthUrl : $yearUrl }}" class="{{ $segment }} text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400">{{ $case->name }}</a>
        @endif
    @endforeach
</div>
