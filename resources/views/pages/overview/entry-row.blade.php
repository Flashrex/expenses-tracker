@use('App\Support\Money')

@php
    $tone = $row->amountCents < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400';
    $hasChips = $row->groupKey !== null || $row->ignored || $row->isShared();
@endphp

<li data-entry="{{ $row->id }}" x-data="entryDescription(@js(['description' => $row->description, 'url' => route('entries.description.update', $row->id)]))" @class(['opacity-50' => $row->ignored])>
    <button type="button" @click="toggle()" :aria-expanded="open" aria-expanded="false" aria-controls="entry-{{ $row->id }}"
        class="flex w-full items-start gap-3 py-2.5 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:hover:bg-slate-800/60 dark:focus-visible:outline-emerald-400">
        <span class="w-12 shrink-0 text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $row->bookedShort }}</span>

        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium" title="{{ $row->merchant }}">{{ $row->merchant }}</span>
            <span data-row-description x-show="saved !== ''" x-text="saved" :title="saved" title="{{ $row->description }}" @if ($row->description === null) style="display: none" @endif
                class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $row->description }}</span>
            @if ($hasChips)
                <span class="mt-1 flex flex-wrap items-center gap-1.5">
                    @if ($row->groupKey !== null)
                        <x-group-chip :group="$row->groupKey" />
                    @endif
                    @if ($row->ignored)
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-heroicon-m-eye-slash class="size-3.5" />ignored</span>
                    @endif
                    @if ($row->isShared())
                        <span title="Your share: 1/{{ $row->shareDivisor }}" class="inline-flex items-center rounded-full border border-slate-200 px-1.5 py-0.5 text-xs font-medium tabular-nums text-slate-600 dark:border-slate-700 dark:text-slate-300">÷{{ $row->shareDivisor }}</span>
                        <span data-full-amount class="text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ Money::format($row->amountCents, true) }}</span>
                    @endif
                </span>
            @endif
        </span>

        <span data-amount @class(['whitespace-nowrap text-right text-sm font-medium tabular-nums', $tone, 'line-through' => $row->ignored])>{{ Money::format($row->isShared() ? $row->countedCents : $row->amountCents, true) }}</span>

        <span class="mt-0.5 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'"><x-heroicon-m-chevron-down class="size-4" /></span>
    </button>

    <x-entry-details id="entry-{{ $row->id }}" :type="$row->type" :counterparty="$row->counterparty" :booked-on="$row->bookedOn" :value-on="$row->valueOn" :purpose="$row->purpose">
        <x-slot:amount>
            @if ($row->isShared())
                {{ Money::format($row->countedCents, true) }} ({{ Money::format($row->amountCents, true) }} / {{ $row->shareDivisor }})
            @else
                {{ Money::format($row->amountCents, true) }}@if ($row->ignored)<span class="text-slate-500 dark:text-slate-400"> · ignored</span>@endif
            @endif
        </x-slot:amount>
        <x-slot:group>
            @if ($row->groupKey !== null)
                <x-group-chip :group="$row->groupKey" />
            @else
                —
            @endif
        </x-slot:group>
        <x-slot:grouped-by>{{ $row->groupedBy }}</x-slot:grouped-by>
        <x-slot:description-field>
            <textarea id="entry-{{ $row->id }}-description" data-description-field x-model="draft" rows="3" maxlength="{{ App\Http\Requests\UpdateEntryDescriptionRequest::MAX_LENGTH }}" placeholder="Add a description…"
                :aria-invalid="error" aria-describedby="entry-{{ $row->id }}-description-error"
                class="block w-full max-w-xl rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">{{ $row->description }}</textarea>
            <p id="entry-{{ $row->id }}-description-error" x-show="error" x-cloak role="alert" class="mt-1 text-xs text-rose-600 dark:text-rose-400">Couldn't save the description. Please try again.</p>
            <div class="mt-2 flex items-center gap-3">
                <button type="button" data-save-description @click="save()" :disabled="! dirty || busy" disabled
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400">Save</button>
                <span x-show="justSaved" x-cloak role="status" class="inline-flex items-center gap-1 text-sm text-emerald-700 dark:text-emerald-400"><x-heroicon-m-check class="size-4" />Saved</span>
            </div>
        </x-slot:description-field>
    </x-entry-details>
</li>
