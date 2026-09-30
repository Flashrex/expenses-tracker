<button type="button" data-entry-toggle @click="open = ! open" :aria-expanded="open" aria-expanded="false" aria-controls="review-entry-{{ $i }}" aria-label="Details for {{ $entry->merchant }}"
    class="-m-1 shrink-0 self-start rounded-md p-1 text-slate-400 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400">
    <span class="block transition" :class="open && 'rotate-180'"><x-heroicon-m-chevron-down class="size-4" /></span>
</button>
