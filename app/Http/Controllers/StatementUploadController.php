<?php

namespace App\Http\Controllers;

use App\Http\Middleware\DiscardPendingStatementImport;
use App\Http\Requests\StoreStatementUploadRequest;
use App\Models\Statement;
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

        session([DiscardPendingStatementImport::SESSION_KEY => $parsed->toArray()]);

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

        return view('pages.upload-review', compact('statement', 'existing'));
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

        DB::transaction(function () use ($parsed) {
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

            $statement->transactions()->createMany(array_map(fn (ParsedEntry $entry) => [
                ...$entry->toArray(),
                'period' => $parsed->period,
                'group_key' => null,
                'share_divisor' => 1,
                'ignored' => false,
                'rule_id' => null,
            ], $parsed->entries));
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
}
