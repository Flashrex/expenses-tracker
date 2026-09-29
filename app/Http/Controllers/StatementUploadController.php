<?php

namespace App\Http\Controllers;

use App\Http\Middleware\DiscardPendingStatementImport;
use App\Http\Requests\StoreStatementUploadRequest;
use App\Models\Statement;
use App\Services\Rules\RuleMatch;
use App\Services\Rules\RuleMatcher;
use App\Services\Statements\IngStatementParser;
use App\Services\Statements\ParsedEntry;
use App\Services\Statements\ParsedStatement;
use App\Services\Statements\StatementParseException;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StatementUploadController extends Controller
{
    /**
     * Show the drop zone and the months imported so far.
     */
    public function create(): View
    {
        return view('pages.upload', [
            'imported' => Statement::query()->whereNotNull('confirmed_at')->orderByDesc('period')->pluck('period'),
        ]);
    }

    /**
     * Parse the uploaded PDF and keep the result in the session for review.
     */
    public function store(StoreStatementUploadRequest $request, IngStatementParser $parser): RedirectResponse
    {
        // The PDF is only read from its temp file, which PHP removes after the request.
        try {
            $parsed = $parser->parse($request->file('statement')->getRealPath());
        } catch (StatementParseException) {
            return back()->withErrors(['statement' => "This doesn't look like an ING statement."]);
        }

        $matches = RuleMatcher::fromDatabase()->matchAll($parsed->entries);

        session([DiscardPendingStatementImport::SESSION_KEY => $parsed->toArray() + [
            'assignments' => array_map(fn (RuleMatch $match) => $match->toArray(), $matches),
        ]]);

        return redirect()->route('upload.review');
    }

    /**
     * Show the pending import.
     */
    public function review(): View|RedirectResponse
    {
        $statement = $this->pending();

        if ($statement === null) {
            return redirect()->route('upload');
        }

        $existing = Statement::query()
            ->where('number', $statement->number)
            ->where('period', $statement->period)
            ->exists();

        $rows = array_map(
            fn (ParsedEntry $entry, RuleMatch $match) => ['entry' => $entry, 'match' => $match],
            $statement->entries,
            $this->pendingAssignments($statement),
        );

        return view('pages.upload-review', compact('statement', 'existing', 'rows'));
    }

    /**
     * Store the pending import, replacing an earlier import of the same statement.
     */
    public function confirm(): RedirectResponse
    {
        $parsed = $this->pending();

        if ($parsed === null) {
            return redirect()->route('upload');
        }

        $matches = $this->pendingAssignments($parsed);

        DB::transaction(function () use ($parsed, $matches) {
            Statement::query()
                ->where('number', $parsed->number)
                ->where('period', $parsed->period)
                ->get()
                ->each(function (Statement $old) {
                    $old->transactions()->delete();
                    $old->delete();
                });

            $statement = Statement::create([
                'number' => $parsed->number,
                'period' => $parsed->period,
                'statement_date' => $parsed->statementDate,
                'old_balance_cents' => $parsed->oldBalanceCents,
                'new_balance_cents' => $parsed->newBalanceCents,
                'confirmed_at' => now(),
            ]);

            $statement->transactions()->createMany(array_map(fn (ParsedEntry $entry, RuleMatch $match) => [
                ...$entry->toArray(),
                'period' => $parsed->period,
                'group_key' => $match->groupKey,
                'share_divisor' => $match->shareDivisor,
                'ignored' => $match->ignored,
                'rule_id' => $match->ruleId,
            ], $parsed->entries, $matches));
        });

        session()->forget(DiscardPendingStatementImport::SESSION_KEY);

        return redirect()->route('upload')
            ->with('status', Period::label($parsed->period).' imported · '.count($parsed->entries).' entries');
    }

    /**
     * Drop the pending import.
     */
    public function discard(): RedirectResponse
    {
        session()->forget(DiscardPendingStatementImport::SESSION_KEY);

        return redirect()->route('upload');
    }

    private function pending(): ?ParsedStatement
    {
        $data = session(DiscardPendingStatementImport::SESSION_KEY);

        return $data === null ? null : ParsedStatement::fromArray($data);
    }

    /**
     * The rule results matched at upload time, one per entry.
     *
     * @return list<RuleMatch>
     */
    private function pendingAssignments(ParsedStatement $statement): array
    {
        $assignments = session(DiscardPendingStatementImport::SESSION_KEY.'.assignments');

        if (! is_array($assignments) || count($assignments) !== count($statement->entries)) {
            return array_map(fn () => RuleMatch::none(), $statement->entries);
        }

        return array_map(RuleMatch::fromArray(...), array_values($assignments));
    }
}
