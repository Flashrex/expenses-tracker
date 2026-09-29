<?php

namespace App\Services\Statements;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Reads an ING "Kontoauszug" PDF by the position of its text chunks.
 */
final class IngStatementParser
{
    private const MONTHS = [
        'Januar' => '01', 'Februar' => '02', 'März' => '03', 'April' => '04',
        'Mai' => '05', 'Juni' => '06', 'Juli' => '07', 'August' => '08',
        'September' => '09', 'Oktober' => '10', 'November' => '11', 'Dezember' => '12',
    ];

    private const DATE = '/^\d{2}\.\d{2}\.\d{4}$/';

    private const AMOUNT = '/^-?\d{1,3}(\.\d{3})*,\d{2}$/';

    /** Chunks within this many points vertically form one line. */
    private const LINE_TOLERANCE = 1.0;

    /** Left edge of the type / purpose column. */
    private const TEXT_COLUMN = 100;

    /** Left edge of the amount column. */
    private const AMOUNT_COLUMN = 480;

    /** Left edge of the page-1 header labels. */
    private const HEADER_COLUMN = 300;

    public function __construct(private MerchantDeriver $merchants = new MerchantDeriver) {}

    public function parse(string $path): ParsedStatement
    {
        try {
            $pages = (new Parser)->parseFile($path)->getPages();
        } catch (Throwable $e) {
            throw new StatementParseException('Unreadable PDF.', previous: $e);
        }

        $header = [];
        $entries = [];
        $current = null;
        $done = false;

        foreach (array_values($pages) as $index => $page) {
            try {
                $lines = $this->lines($page->getDataTm());
            } catch (Throwable $e) {
                throw new StatementParseException('Unreadable PDF.', previous: $e);
            }

            $this->readHeader($lines, $index === 0, $header);

            $inTable = false;

            foreach ($lines as $line) {
                if ($done) {
                    break;
                }

                $text = implode(' ', array_column($line, 't'));

                if (! $inTable) {
                    $inTable = str_starts_with($text, 'Buchung Buchung / Verwendungszweck');

                    continue;
                }

                if ($text === 'Valuta') {
                    continue;
                }

                $first = $line[0];
                $last = $line[array_key_last($line)];
                $isDate = $first['x'] < self::TEXT_COLUMN && preg_match(self::DATE, $first['t']);
                $hasAmount = count($line) > 1 && $last['x'] >= self::AMOUNT_COLUMN && preg_match(self::AMOUNT, $last['t']);
                $inTextColumn = $first['x'] >= self::TEXT_COLUMN && $first['x'] < self::AMOUNT_COLUMN;

                if ($inTextColumn && $first['t'] === 'Neuer Saldo') {
                    $this->close($current, $entries);
                    $done = true;
                } elseif ($isDate && $hasAmount && count($line) >= 3) {
                    $this->close($current, $entries);
                    $counterparty = implode(' ', array_column(array_slice($line, 2, -1), 't'));
                    $current = [
                        'booked' => $first['t'],
                        'value' => null,
                        'type' => $line[1]['t'],
                        'counterparty' => $counterparty === '' ? null : $counterparty,
                        'amount' => $last['t'],
                        'purpose' => [],
                    ];
                } elseif ($current !== null && $current['value'] === null && $isDate) {
                    $current['value'] = $first['t'];
                    $rest = implode(' ', array_column(array_slice($line, 1), 't'));

                    if ($rest !== '') {
                        $current['purpose'][] = $rest;
                    }
                } elseif ($current !== null && $inTextColumn) {
                    $current['purpose'][] = $text;
                } else {
                    $this->close($current, $entries);
                }
            }

            $this->close($current, $entries);
        }

        $missing = array_diff(['number', 'period', 'statement_date', 'old_balance', 'new_balance'], array_keys($header));

        if ($missing !== []) {
            throw new StatementParseException('Missing statement header: '.implode(', ', $missing).'.');
        }

        if ($entries === []) {
            throw new StatementParseException('No entries found.');
        }

        return new ParsedStatement(
            number: $header['number'],
            period: $header['period'],
            statementDate: $header['statement_date'],
            oldBalanceCents: $header['old_balance'],
            newBalanceCents: $header['new_balance'],
            entries: $entries,
        );
    }

    /**
     * Group the page's text chunks into lines, top to bottom, each sorted left to right.
     *
     * @param  array<int, array{0: array<int, string>, 1: string}>  $chunks
     * @return list<non-empty-list<array{x: float, y: float, t: string}>>
     */
    private function lines(array $chunks): array
    {
        $chunks = array_map(fn (array $chunk) => [
            'x' => (float) $chunk[0][4],
            'y' => (float) $chunk[0][5],
            't' => trim(str_replace("\u{00A0}", ' ', $chunk[1])),
        ], $chunks);
        $chunks = array_filter($chunks, fn (array $chunk) => $chunk['t'] !== '');
        usort($chunks, fn (array $a, array $b) => [$b['y'], $a['x']] <=> [$a['y'], $b['x']]);

        $lines = [];
        $lineY = null;

        foreach ($chunks as $chunk) {
            if ($lineY === null || abs($lineY - $chunk['y']) > self::LINE_TOLERANCE) {
                $lines[] = [];
                $lineY = $chunk['y'];
            }

            $lines[array_key_last($lines)][] = $chunk;
        }

        return array_map(function (array $line) {
            usort($line, fn (array $a, array $b) => $a['x'] <=> $b['x']);

            return $line;
        }, $lines);
    }

    /**
     * Collect header values; the first value found wins.
     *
     * @param  list<non-empty-list<array{x: float, y: float, t: string}>>  $lines
     * @param  array<string, int|string>  $header
     */
    private function readHeader(array $lines, bool $firstPage, array &$header): void
    {
        $months = implode('|', array_keys(self::MONTHS));

        foreach ($lines as $line) {
            foreach ($line as $chunk) {
                if (! isset($header['period']) && preg_match("/^Kontoauszug ({$months}) (\d{4})$/u", $chunk['t'], $matches)) {
                    $header['period'] = $matches[2].'-'.self::MONTHS[$matches[1]];
                }
            }

            if (! $firstPage || $line[0]['x'] <= self::HEADER_COLUMN || count($line) < 2) {
                continue;
            }

            $value = $line[array_key_last($line)]['t'];

            match ($line[0]['t']) {
                'Datum' => $header['statement_date'] ??= $this->date($value),
                'Auszugsnummer' => $header['number'] ??= (int) $value,
                'Alter Saldo' => $header['old_balance'] ??= $this->cents(preg_replace('/\s*Euro$/', '', $value)),
                'Neuer Saldo' => $header['new_balance'] ??= $this->cents(preg_replace('/\s*Euro$/', '', $value)),
                default => null,
            };
        }
    }

    /**
     * Turn the entry being read into a ParsedEntry and reset it.
     *
     * @param  array<string, mixed>|null  $current
     * @param  list<ParsedEntry>  $entries
     */
    private function close(?array &$current, array &$entries): void
    {
        if ($current === null) {
            return;
        }

        $purpose = implode("\n", $current['purpose']);

        $entries[] = new ParsedEntry(
            bookedOn: $this->date($current['booked']),
            valueOn: $this->date($current['value'] ?? $current['booked']),
            type: $current['type'],
            counterparty: $current['counterparty'],
            purpose: $purpose,
            merchant: $this->merchants->derive($current['type'], $current['counterparty'], $purpose),
            amountCents: $this->cents($current['amount']),
        );

        $current = null;
    }

    /**
     * "30.06.2026" → "2026-06-30".
     */
    private function date(string $value): string
    {
        if (! preg_match(self::DATE, $value)) {
            throw new StatementParseException("Invalid date \"{$value}\".");
        }

        [$day, $month, $year] = explode('.', $value);

        return "{$year}-{$month}-{$day}";
    }

    /**
     * "-1.158,20" → -115820.
     */
    private function cents(string $value): int
    {
        if (! preg_match(self::AMOUNT, $value)) {
            throw new StatementParseException("Invalid amount \"{$value}\".");
        }

        return (int) str_replace(['.', ','], '', $value);
    }
}
