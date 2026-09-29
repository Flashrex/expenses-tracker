<x-layouts.app title="Trends">
    <x-empty-state
        icon="heroicon-o-chart-bar"
        title="No trends yet"
        text="Trends appear once you have imported a statement."
    >
        <x-slot:action>
            <a href="{{ route('upload') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400">
                <x-heroicon-o-arrow-up-tray class="size-5" /> Upload statement
            </a>
        </x-slot:action>
    </x-empty-state>
</x-layouts.app>
