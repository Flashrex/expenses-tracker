@use('App\Support\Money')
@use('App\Support\Period')

@php
    $primaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400';
    $secondaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:outline-emerald-400';
@endphp

<x-layouts.app title="Review">
    <div class="mx-auto max-w-3xl space-y-4">
        @if ($existing)
            <div role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                <p class="flex items-center gap-3">
                    <x-heroicon-o-exclamation-triangle class="size-5 shrink-0" />
                    {{ Period::label($statement->period) }} (statement {{ $statement->number }}) is already imported. Confirming replaces it.
                </p>

                <form method="POST" action="{{ route('upload.discard') }}">
                    @csrf
                    <button type="submit" class="{{ $secondaryButton }}">Cancel</button>
                </form>
            </div>
        @endif

        <div class="sticky top-16 z-[5] rounded-2xl border border-slate-200 bg-white/90 p-4 shadow-sm backdrop-blur sm:p-6 dark:border-slate-800 dark:bg-slate-900/90">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold">{{ Period::label($statement->period) }}</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Statement {{ $statement->number }} · {{ count($statement->entries) }} entries</p>
                </div>

                <div class="flex gap-2">
                    <form method="POST" action="{{ route('upload.discard') }}">
                        @csrf
                        <button type="submit" class="{{ $secondaryButton }}">Discard</button>
                    </form>

                    <form method="POST" action="{{ route('upload.confirm') }}">
                        @csrf
                        <button type="submit" class="{{ $primaryButton }}">
                            <x-heroicon-o-check class="size-5" />
                            {{ $existing ? 'Replace import' : 'Confirm import' }}
                        </button>
                    </form>
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">Old balance</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{{ Money::format($statement->oldBalanceCents) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">New balance</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{{ Money::format($statement->newBalanceCents) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">In</dt>
                    <dd class="mt-1 font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{{ Money::format($statement->incomingCents(), true) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">Out</dt>
                    <dd class="mt-1 font-semibold tabular-nums text-rose-600 dark:text-rose-400">{{ Money::format($statement->outgoingCents(), true) }}</dd>
                </div>
            </dl>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="sr-only">
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Merchant</th>
                        <th scope="col">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($rows as ['entry' => $entry, 'match' => $match])
                        @php($state = $match->state($entry->direction()))
                        <tr data-assignment="{{ $state }}" @if ($state === 'group') data-group="{{ $match->groupKey }}" @endif @class(['opacity-50' => $state === 'ignored'])>
                            <td class="w-16 px-4 py-2.5 align-top text-slate-500 tabular-nums dark:text-slate-400">{{ \Illuminate\Support\Carbon::parse($entry->bookedOn)->format('d.m.') }}</td>
                            <td class="w-full max-w-0 px-2 py-2.5">
                                <div class="truncate font-medium" title="{{ $entry->merchant }}">{{ $entry->merchant }}</div>

                                @if ($state !== 'income')
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        @if ($state === 'group')
                                            <x-group-chip :group="$match->groupKey" />

                                            @if ($match->shareDivisor > 1)
                                                <span title="Your share: 1/{{ $match->shareDivisor }}" class="inline-flex items-center rounded-full border border-slate-200 px-1.5 py-0.5 text-xs font-medium tabular-nums text-slate-600 dark:border-slate-700 dark:text-slate-300">÷{{ $match->shareDivisor }}</span>
                                            @endif
                                        @elseif ($state === 'ignored')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-heroicon-m-eye-slash class="size-3.5" />ignored</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full border border-dashed border-slate-300 px-2 py-0.5 text-xs font-medium text-slate-500 dark:border-slate-600 dark:text-slate-400">No group</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td @class([
                                'whitespace-nowrap px-4 py-2.5 text-right align-top font-medium tabular-nums',
                                'text-rose-600 dark:text-rose-400' => $entry->amountCents < 0,
                                'text-emerald-600 dark:text-emerald-400' => $entry->amountCents >= 0,
                                'line-through' => $state === 'ignored',
                            ])>{{ Money::format($entry->amountCents, true) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
