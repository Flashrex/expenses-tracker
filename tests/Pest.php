<?php

use App\Models\Statement;
use App\Models\Transaction;
use App\Services\Rules\TextNormalizer;
use App\Services\Statements\IngStatementParser;
use App\Services\Statements\MerchantDeriver;
use App\Services\Statements\ParsedEntry;
use App\Services\Statements\ParsedStatement;
use Database\Seeders\RuleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Feature');

function fixturePath(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

/** Upload of a temp copy of the fixture, so the real fixture is never touched. */
function statementUpload(string $fixture = 'ing-2026-06.pdf'): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'stmt');
    copy(fixturePath($fixture), $tmp);

    return new UploadedFile($tmp, 'statement.pdf', 'application/pdf', null, true);
}

/** A valid one-page PDF that is not an ING statement. */
function blankPdf(): string
{
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        "<< /Length 44 >>\nstream\nBT /F1 24 Tf 72 720 Td (Hello world) Tj ET\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

/** The June fixture, parsed once per process. */
function fixtureStatement(): ParsedStatement
{
    static $statement = null;

    return $statement ??= (new IngStatementParser)->parse(fixturePath('ing-2026-06.pdf'));
}

/** A synthetic entry; the merchant is derived like the parser does it. */
function entry(int $amountCents, ?string $counterparty = null, string $purpose = '', string $type = 'Lastschrift'): ParsedEntry
{
    return new ParsedEntry(
        bookedOn: '2026-06-01',
        valueOn: '2026-06-01',
        type: $type,
        counterparty: $counterparty,
        purpose: $purpose,
        merchant: (new MerchantDeriver)->derive($type, $counterparty, $purpose),
        amountCents: $amountCents,
    );
}

/** Assigns every still-open queue entry of the pending import to $group via the JSON route. */
function assignOpenEntries(string $group = 'other'): void
{
    $import = session('statement_import');

    foreach ($import['assignments'] as $i => $assignment) {
        $merchantKey = TextNormalizer::normalize($import['entries'][$i]['merchant']);
        $open = $assignment['group_key'] === null
            && ! $assignment['ignored']
            && $import['entries'][$i]['amount_cents'] < 0
            && ! isset(($import['picks'] ?? [])[$i])
            && ! isset(($import['always'] ?? [])[$merchantKey]);

        if ($open) {
            test()->postJson(route('upload.assign'), ['entry' => $i, 'group' => $group])->assertOk();
        }
    }
}

/** Imports the June fixture through the real upload flow (seeded rules, open entry → other). Call after actingAs(). */
function importFixtureStatement(): void
{
    test()->seed(RuleSeeder::class);
    test()->post(route('upload.store'), ['statement' => statementUpload()])->assertRedirect(route('upload.review'));
    assignOpenEntries('other');
    test()->post(route('upload.confirm'))->assertRedirect(route('upload'));
}

/**
 * A confirmed statement for $period (number = month) with the given transactions.
 *
 * @param  list<array<string, mixed>>  $transactions  attributes; period and statement are filled in
 */
function importedMonth(string $period, array $transactions = []): Statement
{
    $statement = Statement::factory()->create(['number' => (int) substr($period, 5), 'period' => $period]);

    foreach ($transactions as $attributes) {
        Transaction::factory()->for($statement)->create(['period' => $period] + $attributes);
    }

    return $statement;
}

/** A July 2026 statement next to the June fixture: 11.000 cents spent (groceries 1000, rent 30000 ÷ 3), 5000 income. */
function importJuly(): void
{
    importedMonth('2026-07', [
        ['amount_cents' => -1000, 'group_key' => 'groceries'],
        ['amount_cents' => -30000, 'group_key' => 'rent', 'share_divisor' => 3],
        ['amount_cents' => 5000, 'direction' => 'in'],
    ]);
}
