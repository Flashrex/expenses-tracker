<?php

namespace App\Services\Statements;

final readonly class ParsedStatement
{
    /**
     * @param  list<ParsedEntry>  $entries
     */
    public function __construct(
        public int $number,
        public string $period,
        public string $statementDate,
        public int $oldBalanceCents,
        public int $newBalanceCents,
        public array $entries,
    ) {}

    /**
     * @return array{number: int, period: string, statement_date: string, old_balance_cents: int, new_balance_cents: int, entries: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'period' => $this->period,
            'statement_date' => $this->statementDate,
            'old_balance_cents' => $this->oldBalanceCents,
            'new_balance_cents' => $this->newBalanceCents,
            'entries' => array_map(fn (ParsedEntry $entry) => $entry->toArray(), $this->entries),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            number: $data['number'],
            period: $data['period'],
            statementDate: $data['statement_date'],
            oldBalanceCents: $data['old_balance_cents'],
            newBalanceCents: $data['new_balance_cents'],
            entries: array_values(array_map(ParsedEntry::fromArray(...), $data['entries'])),
        );
    }

    /**
     * Sum of all incoming amounts.
     */
    public function incomingCents(): int
    {
        return array_sum(array_map(fn (ParsedEntry $entry) => max($entry->amountCents, 0), $this->entries));
    }

    /**
     * Sum of all outgoing amounts, as a negative number.
     */
    public function outgoingCents(): int
    {
        return array_sum(array_map(fn (ParsedEntry $entry) => min($entry->amountCents, 0), $this->entries));
    }
}
