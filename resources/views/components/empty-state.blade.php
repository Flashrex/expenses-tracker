@props(['icon', 'title', 'text'])

<div class="mx-auto flex max-w-md flex-col items-center rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex size-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400">
        <x-dynamic-component :component="$icon" class="size-7" />
    </div>

    <h1 class="mt-4 text-lg font-semibold">{{ $title }}</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $text }}</p>

    @isset($action)
        <div class="mt-6">{{ $action }}</div>
    @endisset
</div>
