{{-- The notifications of the `notifications` Alpine store, bottom right; a notification flashed with ->notify() shows on load. --}}
<div data-notifications aria-live="polite" x-data @if (session('notify')) x-init="$store.notifications.push(@js(session('notify')))" @endif
    class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6 sm:bottom-6">
    <template x-for="item in $store.notifications.items" :key="item.id">
        <div :data-notification="item.type" :role="item.type === 'error' ? 'alert' : 'status'"
            x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            :class="{
                'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-950 dark:text-emerald-300': item.type === 'success',
                'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-400/20 dark:bg-rose-950 dark:text-rose-300': item.type === 'error',
                'border-slate-200 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200': item.type === 'info',
            }"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border px-4 py-3 text-sm font-medium shadow-lg">
            <span x-show="item.type === 'success'"><x-heroicon-o-check-circle class="size-5 shrink-0" /></span>
            <span x-show="item.type === 'error'"><x-heroicon-o-exclamation-circle class="size-5 shrink-0" /></span>
            <span x-show="item.type === 'info'"><x-heroicon-o-information-circle class="size-5 shrink-0" /></span>
            <p class="min-w-0 flex-1 break-words" x-text="item.message"></p>
            <button type="button" @click="$store.notifications.dismiss(item.id)" aria-label="Dismiss" title="Dismiss"
                class="-m-1 shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400">
                <x-heroicon-m-x-mark class="size-4" />
            </button>
        </div>
    </template>
</div>
