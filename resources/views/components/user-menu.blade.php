@php
    $item = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:outline-emerald-400';
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="if (open) { open = false; $refs.button.focus() }">
    <button type="button" x-ref="button" data-user-menu-button @click="open = ! open" :aria-expanded="open" aria-expanded="false" aria-haspopup="true" aria-label="Account" title="Account"
        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400">
        <x-heroicon-o-user-circle class="size-6" />
    </button>

    <div data-user-menu x-show="open" x-cloak role="menu" class="absolute top-full right-0 z-20 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
        <p data-user-email class="truncate px-3 py-2 text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
        <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
        <a role="menuitem" href="{{ route('groups') }}" class="{{ $item }}">
            <x-heroicon-o-swatch class="size-5 text-slate-400" />
            Groups &amp; rules
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" role="menuitem" class="{{ $item }}">
                <x-heroicon-o-arrow-right-start-on-rectangle class="size-5 text-slate-400" />
                Log out
            </button>
        </form>
    </div>
</div>
