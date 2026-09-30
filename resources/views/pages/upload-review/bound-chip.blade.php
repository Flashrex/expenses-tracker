{{-- A group chip that follows the group key in $groupExpression; $current is the server-side key for the first paint. --}}
<span class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
    <span class="size-2 shrink-0 rounded-full" :style="`background-color: ${groups[{{ $groupExpression }}]?.color}`" @if ($current !== null) style="background-color: {{ config("expenses.groups.$current.color") }}" @endif></span>
    <span class="truncate" x-text="groups[{{ $groupExpression }}]?.name">{{ $current !== null ? config("expenses.groups.$current.name") : '' }}</span>
</span>
