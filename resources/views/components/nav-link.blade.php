@props(['href', 'active' => false, 'icon', 'label'])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    aria-label="{{ $label }}"
    @class([
        'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' => $active,
        'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' => ! $active,
    ])
>
    <x-dynamic-component :component="$icon" class="size-5" />
    <span class="hidden sm:inline">{{ $label }}</span>
</a>
