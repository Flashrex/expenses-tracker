{{-- How an entry is grouped: $result = ['group_key' => ?string, 'share_divisor' => int, 'ignored' => bool]. --}}
<span class="inline-flex max-w-full flex-wrap items-center gap-1.5">
    @if ($result['ignored'])
        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-heroicon-m-eye-slash class="size-3.5" />ignored</span>
    @elseif ($result['group_key'] !== null)
        <x-group-chip :group="$result['group_key']" />
        @if ($result['share_divisor'] > 1)
            <span title="Your share: 1/{{ $result['share_divisor'] }}" class="inline-flex items-center rounded-full border border-slate-200 px-1.5 py-0.5 text-xs font-medium tabular-nums text-slate-600 dark:border-slate-700 dark:text-slate-300">÷{{ $result['share_divisor'] }}</span>
        @endif
    @else
        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">No group</span>
    @endif
</span>
