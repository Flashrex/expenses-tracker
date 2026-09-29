@props(['label', 'icon', 'value', 'tone' => 'neutral'])

<div data-kpi="{{ Str::lower($label) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        <x-dynamic-component :component="$icon" class="size-5" />
        {{ $label }}
    </div>
    <p @class([
        'mt-2 text-2xl font-semibold tabular-nums',
        'text-emerald-600 dark:text-emerald-400' => $tone === 'positive',
        'text-rose-600 dark:text-rose-400' => $tone === 'negative',
    ])>{{ $value }}</p>
</div>
