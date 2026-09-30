@props(['id', 'type', 'counterparty' => null, 'bookedOn', 'valueOn', 'purpose', 'description' => null])

@php
    $label = 'text-xs text-slate-500 dark:text-slate-400';
@endphp

<div id="{{ $id }}" data-entry-details x-show="open" x-cloak {{ $attributes->class('pb-4 pl-15 text-sm') }}>
    <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-[max-content_1fr_max-content_1fr]">
        <dt class="{{ $label }}">Type</dt>
        <dd>{{ $type }}</dd>
        <dt class="{{ $label }}">Counterparty</dt>
        <dd class="wrap-break-word">{{ $counterparty ?? '—' }}</dd>
        <dt class="{{ $label }}">Booked</dt>
        <dd class="tabular-nums">{{ $bookedOn }}</dd>
        <dt class="{{ $label }}">Value</dt>
        <dd class="tabular-nums">{{ $valueOn }}</dd>
        <dt class="{{ $label }}">Amount</dt>
        <dd data-detail-amount class="tabular-nums">{{ $amount }}</dd>
        <dt class="{{ $label }}">Group</dt>
        <dd>{{ $group }}</dd>
        <dt class="{{ $label }}">Grouped by</dt>
        <dd data-grouped-by class="sm:col-span-3">{{ $groupedBy }}</dd>
    </dl>
    <dl class="mt-3">
        <dt class="{{ $label }}">Purpose</dt>
        <dd data-purpose class="mt-1 whitespace-pre-line wrap-break-word">{{ $purpose === '' ? '—' : $purpose }}</dd>
    </dl>
    <dl class="mt-3">
        @isset($descriptionField)
            <dt class="{{ $label }}"><label for="{{ $id }}-description">Description</label></dt>
            <dd class="mt-1">{{ $descriptionField }}</dd>
        @else
            <dt class="{{ $label }}">Description</dt>
            <dd data-description class="mt-1 whitespace-pre-line wrap-break-word">{{ $description ?? '—' }}</dd>
        @endisset
    </dl>
</div>
