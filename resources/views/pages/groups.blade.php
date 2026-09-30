@use('App\Enums\RuleField')
@use('App\Enums\RuleOperator')

@php
    $primaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400';
    $secondaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:outline-emerald-400';
    $smallPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400';
    $iconButton = 'rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:hover:bg-slate-800 dark:hover:text-slate-200 dark:focus-visible:outline-emerald-400';
    $handle = 'cursor-grab rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-emerald-600 active:cursor-grabbing dark:hover:bg-slate-800 dark:hover:text-slate-200 dark:focus-visible:outline-emerald-400';
    $input = 'rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';
    $fieldError = 'mt-1 text-xs text-rose-600 dark:text-rose-400';
    $menuItem = 'flex w-full items-center rounded-lg px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:outline-emerald-400';
    $unsaved = 'inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-400/15 dark:text-amber-300';

    $fields = [];
    foreach (RuleField::cases() as $field) {
        $fields[$field->value] = [
            'label' => $field->label(),
            'operators' => array_map(fn (RuleOperator $operator) => [$operator->value, $operator->label()], RuleOperator::forField($field)),
        ];
    }

    $payload = [
        'state' => $state,
        'swatches' => ['#6366f1', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899', '#0ea5e9', '#f97316', '#f43f5e', '#14b8a6', '#94a3b8', '#ef4444', '#84cc16', '#06b6d4', '#eab308', '#78716c', '#d946ef'],
        'fields' => $fields,
        'directions' => [['out', 'Outgoing'], ['in', 'Incoming'], ['any', 'Both']],
        'urls' => [
            'store' => route('groups.store'),
            'order' => route('groups.order'),
            'ignored' => route('groups.ignored.update'),
            'update' => route('groups.update', '__KEY__'),
            'destroy' => route('groups.destroy', '__KEY__'),
            'rule' => route('rules.destroy', '__ID__'),
        ],
        'csrf' => csrf_token(),
    ];
@endphp

<x-layouts.app title="Groups & rules">
    <div class="mx-auto max-w-3xl space-y-4" x-data="groupsPage(@js($payload))" data-groups-page>
        @if (session('status'))
            <div role="status" data-status-notice class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300">
                <x-heroicon-o-check-circle class="size-5 shrink-0" />
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">Groups &amp; rules</h1>
        </div>

        <div data-block-bar class="sticky top-16 z-[5] rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Drag a block onto a rule to add a condition, or onto "Add rule" to start a new rule.</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($fields as $value => $field)
                    <span draggable="true" data-block="{{ $value }}" @dragstart="startBlock($event, '{{ $value }}')" @dragend="endBlock()"
                        class="inline-flex cursor-grab items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-800 select-none active:cursor-grabbing dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300">
                        <x-heroicon-m-bars-2 class="size-4 opacity-60" />
                        {{ $field['label'] }}
                    </span>
                @endforeach
            </div>
        </div>

        <p x-show="pageError" x-cloak role="alert" class="text-sm text-rose-600 dark:text-rose-400">Couldn't save. Please try again.</p>

        <div data-group-list class="space-y-4" x-sort="moveCard($item, $position)" x-sort:config="{ handle: '[data-card-handle]', animation: 150 }">
            <template x-for="card in cards" :key="card.uid">
                <section x-sort:item="card.uid" :data-group-card="card.key ?? 'new'" :data-card-uid="card.uid" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2">
                        <button type="button" data-card-handle aria-label="Move group" title="Move group" class="{{ $handle }}">
                            <x-heroicon-m-bars-3 class="size-5" />
                        </button>
                        <span class="size-3 shrink-0 rounded-full" :style="`background-color: ${card.color}`"></span>
                        <input type="text" data-name maxlength="40" x-model="card.name" placeholder="Group name" aria-label="Group name"
                            class="{{ $input }} min-w-0 flex-1 font-medium">
                        <span data-unsaved x-show="isDirty(card)" x-cloak class="{{ $unsaved }} hidden sm:inline-flex">Unsaved changes</span>
                        <button type="button" data-save @click="save(card)" :disabled="card.saving" class="{{ $smallPrimary }}">Save</button>
                        <button type="button" data-delete-group x-show="! card.isOther" @click="askDelete(card)" aria-label="Delete group" title="Delete group" class="{{ $iconButton }} hover:text-rose-600 dark:hover:text-rose-400">
                            <x-heroicon-o-trash class="size-5" />
                        </button>
                    </div>
                    <span x-show="isDirty(card)" x-cloak class="{{ $unsaved }} mt-2 sm:hidden">Unsaved changes</span>
                    <p x-show="error(card, 'name')" x-text="error(card, 'name')" x-cloak class="{{ $fieldError }}"></p>
                    <p x-show="card.failed" x-cloak role="alert" class="{{ $fieldError }}">Couldn't save. Please try again.</p>

                    <div role="radiogroup" aria-label="Colour" class="mt-3 flex flex-wrap items-center gap-2">
                        <template x-for="swatch in swatches" :key="swatch">
                            <button type="button" role="radio" data-swatch :data-color="swatch" :aria-checked="card.color === swatch" :aria-label="swatch" @click="card.color = swatch"
                                :style="`background-color: ${swatch}`"
                                :class="card.color === swatch ? 'ring-2 ring-slate-900 ring-offset-2 dark:ring-white dark:ring-offset-slate-900' : ''"
                                class="size-6 rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400"></button>
                        </template>
                        <label data-swatch-custom title="Custom colour"
                            :class="isCustomColor(card) ? 'ring-2 ring-slate-900 ring-offset-2 dark:ring-white dark:ring-offset-slate-900' : ''"
                            class="relative size-6 cursor-pointer rounded-full bg-[conic-gradient(#ef4444,#eab308,#22c55e,#06b6d4,#6366f1,#d946ef,#ef4444)] focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-emerald-600">
                            <span class="sr-only">Custom colour</span>
                            <input type="color" class="sr-only" :value="card.color" @input="card.color = $event.target.value.toLowerCase()">
                        </label>
                    </div>
                    <p x-show="error(card, 'color')" x-text="error(card, 'color')" x-cloak class="{{ $fieldError }}"></p>

                    @include('pages.groups.rules', ['withShare' => true])
                </section>
            </template>
        </div>

        <button type="button" data-add-group @click="addGroup()" class="{{ $secondaryButton }} w-full">
            <x-heroicon-m-plus class="size-4" />
            Add group
        </button>

        <section data-ignored-card x-data="{ get card() { return ignored } }" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-2">
                <x-heroicon-o-eye-slash class="size-5 text-slate-400" />
                <h2 class="flex-1 font-semibold">Ignored</h2>
                <span data-unsaved x-show="isDirty(card)" x-cloak class="{{ $unsaved }}">Unsaved changes</span>
                <button type="button" data-save @click="save(card)" :disabled="card.saving" class="{{ $smallPrimary }}">Save</button>
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Entries matching these rules don't count anywhere.</p>
            <p x-show="card.failed" x-cloak role="alert" class="{{ $fieldError }}">Couldn't save. Please try again.</p>

            @include('pages.groups.rules', ['withShare' => false])
        </section>

        <div x-show="deleting" x-cloak role="dialog" aria-modal="true" aria-labelledby="delete-title" data-delete-dialog
            class="fixed inset-0 z-30 flex items-center justify-center bg-slate-950/40 p-4" @keydown.escape.window="deleting = null">
            <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900" @click.outside="deleting = null">
                <template x-if="deleting">
                    <div class="space-y-4">
                        <h2 id="delete-title" class="text-base font-semibold" x-text="`Delete &quot;${deleting.name}&quot;?`"></h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Its rules and "Always use" rules are deleted too.</p>
                        <label class="block text-sm">
                            <span class="font-medium">Move its entries to:</span>
                            <select data-move-to x-model="moveTo" class="{{ $input }} mt-1 w-full">
                                <template x-for="group in savedGroups(deleting)" :key="group.key">
                                    <option :value="group.key" x-text="group.name" :selected="group.key === moveTo"></option>
                                </template>
                            </select>
                        </label>
                        <p x-show="deleteFailed" role="alert" class="text-sm text-rose-600 dark:text-rose-400">Couldn't delete. Please try again.</p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="deleting = null" class="{{ $secondaryButton }}">Cancel</button>
                            <button type="button" data-confirm-delete @click="confirmDelete()" :disabled="deleteBusy"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:opacity-50 dark:bg-rose-500 dark:hover:bg-rose-400">
                                Delete group
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-layouts.app>
