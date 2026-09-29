@use('App\Support\Money')
@use('App\Support\Period')

@php
    $primaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400';
    $pickSelected = 'border-(--group) bg-(--group)/15 ring-1 ring-(--group) text-slate-900 dark:text-slate-100';
    $pickUnselected = 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600';
    $secondaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:outline-emerald-400';
@endphp

<x-layouts.app title="Review">
    <div class="mx-auto max-w-3xl space-y-4" x-data="reviewQueue(@js(['state' => $queueState, 'assignUrl' => route('upload.assign', $period), 'alwaysUrl' => route('upload.always', $period), 'csrf' => csrf_token()]))">
        @if ($failed !== [])
            <div role="status" data-failed-notice x-data="{ open: true }" x-show="open" class="flex items-start justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                <div class="flex min-w-0 gap-3">
                    <x-heroicon-o-exclamation-triangle class="size-5 shrink-0" />
                    <div class="min-w-0">
                        <p class="font-semibold">Couldn't import {{ count($failed) }} {{ Str::plural('file', count($failed)) }}</p>
                        <ul class="mt-1 space-y-0.5 break-words">
                            @foreach ($failed as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <form method="POST" action="{{ route('upload.notice.dismiss') }}" @submit.prevent="open = false; fetch($el.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })">
                    @csrf
                    <button type="submit" aria-label="Dismiss" class="-m-1 rounded-md p-1 text-amber-700 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:text-amber-300 dark:hover:bg-amber-400/10 dark:focus-visible:outline-emerald-400">
                        <x-heroicon-m-x-mark class="size-5" />
                    </button>
                </form>
            </div>
        @endif

        @if (session('review_error'))
            <div role="alert" data-review-error class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300">
                <x-heroicon-o-exclamation-circle class="size-5 shrink-0" />
                {{ session('review_error') }}
            </div>
        @endif

        @if ($existing)
            <div role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                <p class="flex items-center gap-3">
                    <x-heroicon-o-exclamation-triangle class="size-5 shrink-0" />
                    {{ Period::label($statement->period) }} (statement {{ $statement->number }}) is already imported. Confirming replaces it.
                </p>

                <form method="POST" action="{{ $isBatch ? route('upload.skip', $period) : route('upload.discard') }}">
                    @csrf
                    <button type="submit" class="{{ $secondaryButton }}">{{ $isBatch ? 'Skip this month' : 'Cancel' }}</button>
                </form>
            </div>
        @endif

        @if ($isBatch)
            <nav aria-label="Import months" data-stepper>
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    @foreach ($steps as $step)
                        @if (! $loop->first)
                            <li aria-hidden="true" class="text-slate-400 dark:text-slate-600">·</li>
                        @endif

                        <li data-step="{{ $step['period'] }}" data-step-state="{{ $step['state'] }}">
                            @if ($step['period'] === $period)
                                <span aria-current="step" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-semibold dark:bg-slate-800">
                                    {{ $step['label'] }}
                                    <span x-show="state.open > 0" @style(['display: none' => $step['open'] === 0]) class="rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/10 dark:text-amber-300" x-text="state.open">{{ $step['open'] }}</span>
                                    <x-heroicon-m-check-circle x-show="state.open === 0" @style(['display: none' => $step['open'] > 0]) class="size-4 text-emerald-600 dark:text-emerald-400" />
                                </span>
                            @elseif ($step['state'] === 'confirmed')
                                <span class="inline-flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                    {{ $step['label'] }}
                                    <x-heroicon-m-check class="size-4 text-emerald-600 dark:text-emerald-400" />
                                </span>
                            @elseif ($step['state'] === 'skipped')
                                <span class="inline-flex items-center gap-1.5 text-slate-500 line-through dark:text-slate-400">
                                    {{ $step['label'] }}
                                    <x-heroicon-m-minus-circle class="size-4" />
                                </span>
                            @else
                                <a href="{{ route('upload.review', $step['period']) }}" class="inline-flex items-center gap-1.5 rounded-sm hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400">
                                    {{ $step['label'] }}
                                    @if ($step['state'] === 'open')
                                        <span class="rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/10 dark:text-amber-300">{{ $step['open'] }}</span>
                                    @else
                                        <x-heroicon-m-check-circle class="size-4 text-emerald-600 dark:text-emerald-400" />
                                    @endif
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <div class="sticky top-16 z-[5] rounded-2xl border border-slate-200 bg-white/90 p-4 shadow-sm backdrop-blur sm:p-6 dark:border-slate-800 dark:bg-slate-900/90">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold">{{ Period::label($statement->period) }}</h1>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Statement {{ $statement->number }} · {{ count($statement->entries) }} entries</p>

                        @if (count($queueRows) > 0)
                            <span data-review-counter x-show="state.open > 0" @style(['display: none' => $open === 0]) class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/10 dark:text-amber-300"><span x-text="state.open">{{ $open }}</span> to review</span>
                            <span data-review-done x-show="state.open === 0" @style(['display: none' => $open > 0]) class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-300"><x-heroicon-m-check class="size-3.5" />All assigned</span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($isBatch)
                        <form method="POST" action="{{ route('upload.skip', $period) }}">
                            @csrf
                            <button type="submit" class="{{ $secondaryButton }}">Skip</button>
                        </form>

                        <form method="POST" action="{{ route('upload.discard') }}">
                            @csrf
                            <button type="submit" class="{{ $secondaryButton }}">Discard remaining</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('upload.discard') }}">
                            @csrf
                            <button type="submit" class="{{ $secondaryButton }}">Discard</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('upload.confirm', $period) }}">
                        @csrf
                        <button type="submit" data-confirm @disabled($open > 0) :disabled="state.open > 0" class="{{ $primaryButton }} disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-emerald-600 dark:disabled:hover:bg-emerald-500">
                            <x-heroicon-o-check class="size-5" />
                            {{ $existing ? 'Replace import' : 'Confirm import' }}
                        </button>
                    </form>
                </div>
            </div>

            <p x-show="state.open > 0" @style(['display: none' => $open === 0]) class="mt-2 text-xs text-slate-500 sm:text-right dark:text-slate-400">Assign a group to every entry to confirm.</p>

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

        @if (count($queueRows) > 0)
            <section aria-labelledby="review-heading" class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 id="review-heading" class="flex items-center gap-2 px-4 pt-4 text-sm font-semibold">
                    <x-heroicon-o-queue-list class="size-5 text-amber-600 dark:text-amber-400" /> To review
                </h2>
                <p x-show="error" x-cloak role="alert" class="px-4 pt-2 text-sm text-rose-600 dark:text-rose-400">Couldn't save your choice. Please try again.</p>

                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($queueRows as ['index' => $i, 'entry' => $entry])
                        <li data-review-entry="{{ $i }}" class="space-y-3 px-4 py-3">
                            <div class="flex items-baseline gap-3">
                                <span class="w-12 shrink-0 text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Carbon::parse($entry->bookedOn)->format('d.m.') }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium" title="{{ $entry->merchant }}">{{ $entry->merchant }}</span>
                                <span class="whitespace-nowrap text-sm font-medium tabular-nums text-rose-600 dark:text-rose-400">{{ Money::format($entry->amountCents, true) }}</span>
                            </div>

                            <div role="group" aria-label="Group for {{ $entry->merchant }}" class="flex flex-wrap gap-1.5">
                                @foreach (config('expenses.groups') as $key => $group)
                                    @php($selected = $queueState['entries'][$i]['group'] === $key)
                                    <button type="button" data-pick="{{ $key }}"
                                        style="--group: {{ $group['color'] }}"
                                        @click="pick({{ $i }}, '{{ $key }}')"
                                        :disabled="busy"
                                        :aria-pressed="state.entries[{{ $i }}].group === '{{ $key }}'"
                                        aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                        :class="state.entries[{{ $i }}].group === '{{ $key }}'
                                            ? 'border-(--group) bg-(--group)/15 ring-1 ring-(--group) text-slate-900 dark:text-slate-100'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600'"
                                        @class([
                                            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait dark:focus-visible:outline-emerald-400',
                                            $pickSelected => $selected,
                                            $pickUnselected => ! $selected,
                                        ])>
                                        <span class="size-2 shrink-0 rounded-full bg-(--group)"></span>{{ $group['name'] }}
                                    </button>
                                @endforeach
                            </div>

                            <label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400" :class="state.entries[{{ $i }}].group === null && 'opacity-50'">
                                <input type="checkbox" data-always
                                    class="size-4 rounded border-slate-300 text-emerald-600 focus-visible:outline-emerald-600 dark:border-slate-600 dark:bg-slate-800"
                                    :checked="state.entries[{{ $i }}].always"
                                    :disabled="busy || state.entries[{{ $i }}].group === null"
                                    @change="toggleAlways({{ $i }}, $event)"
                                    @checked($queueState['entries'][$i]['always'])
                                    @disabled($queueState['entries'][$i]['group'] === null)>
                                <span>Always use this group for <span class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->merchant }}</span></span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (count($rows) > 0)
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
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-heroicon-m-eye-slash class="size-3.5" />ignored</span>
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
        @endif
    </div>
</x-layouts.app>
