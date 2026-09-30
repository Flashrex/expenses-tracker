@use('App\Support\Money')
@use('App\Support\Period')

@php
    $primaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400';
    $secondaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:outline-emerald-400';
    $toggle = 'rounded-md px-2.5 py-1 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400';
    $toggleOff = 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';
    $acceptOn = 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-slate-950';
    $declineOn = 'bg-slate-700 text-white dark:bg-slate-200 dark:text-slate-900';
@endphp

<x-layouts.app title="Rerun all rules">
    <div class="mx-auto max-w-3xl space-y-4" x-data="rerunPreview(@js([
        'decisions' => (object) $decisions,
        'ids' => $ids,
        'decideUrl' => route('groups.rerun.decide'),
        'csrf' => csrf_token(),
    ]))">
        @if (session('rerun_error'))
            <div role="alert" data-rerun-error class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300">
                <x-heroicon-o-exclamation-circle class="size-5 shrink-0" />
                {{ session('rerun_error') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div>
                <h1 class="text-lg font-semibold">Proposed changes</h1>
                <p data-undecided class="text-sm text-slate-500 dark:text-slate-400" x-text="undecided === 0 ? 'All decided' : undecided + ' undecided'">{{ $undecided === 0 ? 'All decided' : $undecided.' undecided' }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" data-accept-all @click="decide(ids, 'accept')" :disabled="busy" class="{{ $secondaryButton }}">Accept all</button>
                <button type="button" data-decline-all @click="decide(ids, 'decline')" :disabled="busy" class="{{ $secondaryButton }}">Decline all</button>
            </div>
        </div>

        <p x-show="error" x-cloak role="alert" class="text-sm text-rose-600 dark:text-rose-400">Couldn't save your choice. Please try again.</p>

        @foreach ($months as $period => $rows)
            <section data-rerun-month="{{ $period }}" class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="border-b border-slate-100 px-4 py-3 font-semibold dark:border-slate-800">{{ Period::label($period) }}</h2>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($rows as $row)
                        @php
                            $proposed = $row['proposed'];
                            $isShared = $proposed['share_divisor'] > 1 && ! $proposed['ignored'];
                            $counted = (int) round($row['amountCents'] / $proposed['share_divisor'], 0, PHP_ROUND_HALF_EVEN);
                            $decision = $decisions[$row['id']] ?? null;
                        @endphp
                        <li data-rerun-row="{{ $row['id'] }}" x-data="{ open: false }" class="px-4">
                            <div @click="togglesRow($event) && (open = ! open)" class="flex cursor-pointer flex-wrap items-start gap-x-3 gap-y-2 py-2.5 text-sm">
                                <span class="w-12 shrink-0 tabular-nums text-slate-500 dark:text-slate-400">{{ $row['bookedShort'] }}</span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start gap-3">
                                        <span class="min-w-0 flex-1 truncate font-medium" title="{{ $row['merchant'] }}">{{ $row['merchant'] }}</span>
                                        <span @class(['whitespace-nowrap text-right font-medium tabular-nums', 'text-rose-600 dark:text-rose-400' => $row['amountCents'] < 0, 'text-emerald-600 dark:text-emerald-400' => $row['amountCents'] >= 0])>{{ Money::format($row['amountCents'], true) }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span data-current>@include('pages.groups-rerun.chip', ['result' => $row['current']])</span>
                                        <x-heroicon-m-arrow-right class="size-3.5 shrink-0 text-slate-400" />
                                        <span data-proposed>@include('pages.groups-rerun.chip', ['result' => $proposed])</span>
                                    </div>
                                </div>

                                <div role="group" aria-label="Decision" class="inline-flex shrink-0 rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                                    <button type="button" data-accept @click="decide(['{{ $row['id'] }}'], 'accept')" :disabled="busy"
                                        :aria-pressed="decisions['{{ $row['id'] }}'] === 'accept'" aria-pressed="{{ $decision === 'accept' ? 'true' : 'false' }}"
                                        :class="decisions['{{ $row['id'] }}'] === 'accept' ? '{{ $acceptOn }}' : '{{ $toggleOff }}'"
                                        class="{{ $toggle }} {{ $decision === 'accept' ? $acceptOn : $toggleOff }}">Accept</button>
                                    <button type="button" data-decline @click="decide(['{{ $row['id'] }}'], 'decline')" :disabled="busy"
                                        :aria-pressed="decisions['{{ $row['id'] }}'] === 'decline'" aria-pressed="{{ $decision === 'decline' ? 'true' : 'false' }}"
                                        :class="decisions['{{ $row['id'] }}'] === 'decline' ? '{{ $declineOn }}' : '{{ $toggleOff }}'"
                                        class="{{ $toggle }} {{ $decision === 'decline' ? $declineOn : $toggleOff }}">Decline</button>
                                </div>

                                <button type="button" @click="open = ! open" :aria-expanded="open" aria-expanded="false" aria-controls="rerun-entry-{{ $row['id'] }}" aria-label="Details for {{ $row['merchant'] }}"
                                    class="-m-1 shrink-0 self-start rounded-md p-1 text-slate-400 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400">
                                    <span class="block transition" :class="open && 'rotate-180'"><x-heroicon-m-chevron-down class="size-4" /></span>
                                </button>
                            </div>

                            <x-entry-details id="rerun-entry-{{ $row['id'] }}" :type="$row['type']" :counterparty="$row['counterparty']" :booked-on="$row['bookedOn']" :value-on="$row['valueOn']" :purpose="$row['purpose']">
                                <x-slot:amount>@if ($isShared){{ Money::format($counted, true) }} ({{ Money::format($row['amountCents'], true) }} / {{ $proposed['share_divisor'] }})@else{{ Money::format($row['amountCents'], true) }}@if ($proposed['ignored'])<span class="text-slate-500 dark:text-slate-400"> · ignored</span>@endif @endif</x-slot:amount>
                                <x-slot:group>@if ($proposed['group_key'] !== null && ! $proposed['ignored'])<x-group-chip :group="$proposed['group_key']" />@else—@endif</x-slot:group>
                                <x-slot:grouped-by>{{ $row['ruleText'] }}</x-slot:grouped-by>
                            </x-entry-details>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <div class="flex justify-end gap-2">
            <form method="POST" action="{{ route('groups.rerun.cancel') }}">
                @csrf
                <button type="submit" data-cancel class="{{ $secondaryButton }}">Cancel</button>
            </form>
            <form method="POST" action="{{ route('groups.rerun.apply') }}">
                @csrf
                <button type="submit" data-apply :disabled="undecided > 0" @disabled($undecided > 0) class="{{ $primaryButton }} disabled:cursor-not-allowed disabled:opacity-50">Apply</button>
            </form>
        </div>
    </div>
</x-layouts.app>
