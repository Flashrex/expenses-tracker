@php
    $arrow = 'rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400';
    $disabled = 'rounded-lg p-2 text-slate-300 dark:text-slate-700';
    $pageLink = fn (int $page) => route('overview', $period->query() + $filters->withPage($page)->query()).'#entries';
@endphp

<nav aria-label="Entries pages" class="mt-4 flex items-center justify-center gap-1">
    @if ($entries->page > 1)
        <a data-entries-link data-page-nav="prev" href="{{ $pageLink($entries->page - 1) }}" aria-label="Previous page" class="{{ $arrow }}"><x-heroicon-o-chevron-left class="size-5" /></a>
    @else
        <span data-page-nav="prev" aria-disabled="true" class="{{ $disabled }}"><x-heroicon-o-chevron-left class="size-5" /></span>
    @endif

    @foreach ($entries->window() as $page)
        @if ($page === null)
            <span class="px-1 text-slate-400">…</span>
        @elseif ($page === $entries->page)
            <span aria-current="page" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 tabular-nums dark:bg-emerald-400/10 dark:text-emerald-400">{{ $page }}</span>
        @else
            <a data-entries-link data-page="{{ $page }}" href="{{ $pageLink($page) }}" class="rounded-lg px-3 py-1.5 text-sm tabular-nums text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ $page }}</a>
        @endif
    @endforeach

    @if ($entries->page < $entries->lastPage())
        <a data-entries-link data-page-nav="next" href="{{ $pageLink($entries->page + 1) }}" aria-label="Next page" class="{{ $arrow }}"><x-heroicon-o-chevron-right class="size-5" /></a>
    @else
        <span data-page-nav="next" aria-disabled="true" class="{{ $disabled }}"><x-heroicon-o-chevron-right class="size-5" /></span>
    @endif
</nav>
