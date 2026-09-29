<?php

namespace Tests\Support;

use App\Services\Statements\IngStatementParser;
use App\Services\Statements\ParsedEntry;
use App\Services\Statements\ParsedStatement;
use Carbon\CarbonImmutable;

/**
 * Parses for real, then moves the statement into the period registered for the uploaded temp file,
 * so one fixture can stand in for several months.
 */
final class RedatingStatementParser extends IngStatementParser
{
    /** @var array<string, string> temp path => period */
    private array $periods = [];

    public function redate(string $path, string $period): void
    {
        $this->periods[$path] = $period;
    }

    public function parse(string $path): ParsedStatement
    {
        $statement = parent::parse($path);
        $period = $this->periods[$path] ?? null;

        if ($period === null) {
            return $statement;
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $period);

        return new ParsedStatement(
            number: (int) substr($period, 5),
            period: $period,
            statementDate: $month->endOfMonth()->toDateString(),
            oldBalanceCents: $statement->oldBalanceCents,
            newBalanceCents: $statement->newBalanceCents,
            entries: array_map(fn (ParsedEntry $entry) => new ParsedEntry(
                bookedOn: $this->moveInto($month, $entry->bookedOn),
                valueOn: $this->moveInto($month, $entry->valueOn),
                type: $entry->type,
                counterparty: $entry->counterparty,
                purpose: $entry->purpose,
                merchant: $entry->merchant,
                amountCents: $entry->amountCents,
            ), $statement->entries),
        );
    }

    /**
     * Same day of the month, clamped to the month's last day.
     */
    private function moveInto(CarbonImmutable $month, string $date): string
    {
        $day = min(CarbonImmutable::parse($date)->day, $month->daysInMonth);

        return $month->setDay($day)->toDateString();
    }
}
