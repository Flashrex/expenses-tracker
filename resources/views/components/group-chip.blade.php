@props(['group'])

@php
    $config = app(\App\Services\Groups\GroupCatalog::class)->get($group) ?? ['name' => $group, 'color' => '#cbd5e1'];
@endphp

<span {{ $attributes->class('inline-flex max-w-full items-center gap-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}>
    <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $config['color'] }}"></span>
    <span class="truncate">{{ $config['name'] }}</span>
</span>
