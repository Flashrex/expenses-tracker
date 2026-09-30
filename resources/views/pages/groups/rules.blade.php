{{-- The rules of the card in scope as `card`, its "Add rule" button and, for groups, its "Always use" rules. --}}
@php
    $fieldMenu = function (string $pick) use ($menuItem, $fields) {
        $buttons = '';
        foreach ($fields as $value => $field) {
            $buttons .= '<button type="button" role="menuitem" data-menu-field="'.$value.'" @click="open = false; '.$pick.'(\''.$value.'\')" class="'.$menuItem.'">'.e($field['label']).'</button>';
        }

        return '<div x-show="open" x-cloak role="menu" data-field-menu class="absolute top-full left-0 z-20 mt-1 w-44 rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">'.$buttons.'</div>';
    };
@endphp

<div class="mt-4 space-y-2" x-sort="moveRule(card, $item, $position)" x-sort:config="{ handle: '[data-rule-handle]', animation: 150 }">
    <template x-for="(rule, i) in card.rules" :key="rule.uid">
        <div x-sort:item="rule.uid" data-rule
            @dragover="acceptBlock($event, rule.uid)" @dragleave="leaveBlock($event, rule.uid)" @drop="dropOnRule(rule, $event)"
            :class="dropTarget === rule.uid ? 'ring-2 ring-emerald-500 border-emerald-500' : 'border-slate-200 dark:border-slate-700'"
            class="rounded-xl border bg-slate-50/60 p-3 dark:bg-slate-950/40">
            <div class="flex items-start gap-2">
                <button type="button" data-rule-handle aria-label="Move rule" title="Move rule" class="{{ $handle }} -ml-1">
                    <x-heroicon-m-bars-2 class="size-4" />
                </button>

                <div class="min-w-0 flex-1 space-y-1.5">
                    <template x-for="(condition, j) in rule.conditions" :key="condition.uid">
                        <div data-condition>
                            <p x-show="j > 0" class="mb-1.5 text-xs font-semibold tracking-wide text-slate-400 uppercase">And</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <span data-condition-field class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300" x-text="fields[condition.field].label"></span>
                                <select data-operator x-model="condition.operator" aria-label="Operator" class="{{ $input }}">
                                    <template x-for="operator in fields[condition.field].operators" :key="operator[0]">
                                        <option :value="operator[0]" x-text="operator[1]" :selected="operator[0] === condition.operator"></option>
                                    </template>
                                </select>
                                <template x-if="condition.field === 'amount'">
                                    <span class="flex min-w-0 flex-1 items-center gap-1.5">
                                        <input type="text" inputmode="decimal" data-value x-model="condition.value" placeholder="0,00" aria-label="Amount" class="{{ $input }} w-28 tabular-nums">
                                        <span class="text-sm text-slate-500 dark:text-slate-400">€</span>
                                    </span>
                                </template>
                                <template x-if="condition.field !== 'amount'">
                                    <input type="text" maxlength="100" data-value x-model="condition.value" aria-label="Value" class="{{ $input }} min-w-0 flex-1 basis-40">
                                </template>
                                <button type="button" data-remove-condition @click="removeCondition(rule, condition.uid)" aria-label="Remove condition" title="Remove condition" class="{{ $iconButton }}">
                                    <x-heroicon-m-x-mark class="size-4" />
                                </button>
                            </div>
                            <p x-show="error(card, `rules.${i}.conditions.${j}.operator`)" x-text="error(card, `rules.${i}.conditions.${j}.operator`)" x-cloak class="{{ $fieldError }}"></p>
                            <p x-show="error(card, `rules.${i}.conditions.${j}.value`)" x-text="error(card, `rules.${i}.conditions.${j}.value`)" x-cloak class="{{ $fieldError }}"></p>
                        </div>
                    </template>
                    <p x-show="error(card, `rules.${i}.conditions`)" x-text="error(card, `rules.${i}.conditions`)" x-cloak class="{{ $fieldError }}"></p>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pt-1">
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                            <button type="button" data-add-condition @click="open = ! open" :aria-expanded="open" aria-haspopup="true"
                                class="rounded-lg px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-emerald-400 dark:hover:bg-emerald-400/10">+ AND</button>
                            {!! $fieldMenu('addCondition(rule, ') !!}
                        </div>
                        <label class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                            <span class="sr-only">Direction</span>
                            <select data-direction x-model="rule.direction" class="{{ $input }}">
                                <template x-for="direction in directions" :key="direction[0]">
                                    <option :value="direction[0]" x-text="direction[1]" :selected="direction[0] === rule.direction"></option>
                                </template>
                            </select>
                        </label>
                        @if ($withShare)
                            <label class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                Your share 1/
                                <input type="number" min="1" max="10" step="1" data-share x-model="rule.share" aria-label="Share divisor" class="{{ $input }} w-16 tabular-nums">
                            </label>
                        @endif
                    </div>
                    <p x-show="error(card, `rules.${i}.direction`)" x-text="error(card, `rules.${i}.direction`)" x-cloak class="{{ $fieldError }}"></p>
                    @if ($withShare)
                        <p x-show="error(card, `rules.${i}.share`)" x-text="error(card, `rules.${i}.share`)" x-cloak class="{{ $fieldError }}"></p>
                    @endif
                </div>

                <button type="button" data-delete-rule @click="removeRule(card, rule.uid)" aria-label="Delete rule" title="Delete rule" class="{{ $iconButton }} hover:text-rose-600 dark:hover:text-rose-400">
                    <x-heroicon-m-x-mark class="size-5" />
                </button>
            </div>
        </div>
    </template>
</div>

<div class="relative mt-2" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
    <button type="button" data-add-rule @click="open = ! open" :aria-expanded="open" aria-haspopup="true"
        @dragover="acceptBlock($event, `add-${card.uid}`)" @dragleave="leaveBlock($event, `add-${card.uid}`)" @drop="dropOnAddRule(card, $event)"
        :class="dropTarget === `add-${card.uid}` ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' : 'border-slate-300 text-slate-600 dark:border-slate-700 dark:text-slate-300'"
        class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed px-3 py-2 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:hover:bg-slate-800">
        <x-heroicon-m-plus class="size-4" />
        Add rule
    </button>
    {!! $fieldMenu('addRule(card, ') !!}
</div>

@if ($withShare)
    <ul x-show="card.always.length" class="mt-3 space-y-1.5">
        <template x-for="rule in card.always" :key="rule.id">
            <li data-always-rule class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950/40">
                <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300">Always use</span>
                <span class="min-w-0 flex-1 truncate font-mono text-xs text-slate-600 dark:text-slate-300" x-text="rule.text" :title="rule.text"></span>
                <button type="button" @click="deleteAlways(card, rule)" aria-label="Delete Always use rule" title="Delete Always use rule" class="{{ $iconButton }} hover:text-rose-600 dark:hover:text-rose-400">
                    <x-heroicon-o-trash class="size-4" />
                </button>
            </li>
        </template>
    </ul>
@endif
