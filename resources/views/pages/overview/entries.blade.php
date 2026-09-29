<section id="entries" aria-labelledby="entries-heading"
    x-data="entriesCard"
    data-page-url="{{ route('overview') }}"
    data-fragment-url="{{ route('overview.entries') }}"
    class="scroll-mt-20 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="entries-heading" class="text-sm font-semibold">Entries</h2>
        <form role="search" method="GET" action="{{ route('overview') }}#entries" @submit.prevent="search()" class="w-full sm:w-72">
            @foreach (Illuminate\Support\Arr::except($period->query() + $filters->query(), ['q', 'page']) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label class="relative block">
                <span class="sr-only">Search entries</span>
                <x-heroicon-m-magnifying-glass class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input x-ref="search" type="search" name="q" value="{{ $filters->search }}" maxlength="{{ App\Services\Reports\EntryFilters::MAX_QUERY_LENGTH }}" autocomplete="off"
                    placeholder="Search entries" @input.debounce.300ms="search()"
                    class="w-full rounded-lg border border-slate-200 bg-white py-2 pr-3 pl-9 text-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:placeholder:text-slate-500">
            </label>
        </form>
    </div>

    <div x-ref="body" data-entries-body class="transition-opacity" :class="busy && 'opacity-60'" :aria-busy="busy">
        @include('pages.overview.entries-body')
    </div>
</section>
