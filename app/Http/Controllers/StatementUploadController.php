<?php

namespace App\Http\Controllers;

use App\Enums\RuleDirection;
use App\Enums\RuleField;
use App\Enums\RuleSource;
use App\Http\Middleware\DiscardPendingStatementImport;
use App\Http\Requests\AssignReviewEntryRequest;
use App\Http\Requests\StoreStatementUploadRequest;
use App\Http\Requests\ToggleAlwaysRuleRequest;
use App\Models\Rule;
use App\Models\Statement;
use App\Services\Rules\RuleMatch;
use App\Services\Rules\RuleMatcher;
use App\Services\Statements\IngStatementParser;
use App\Services\Statements\ParsedEntry;
use App\Services\Statements\ParsedStatement;
use App\Services\Statements\ReviewQueue;
use App\Services\Statements\StatementParseException;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        $queue = $this->reviewQueue($statement);
        $matches = $this->pendingAssignments($statement);
        $queueRows = [];
        $rows = [];

        foreach ($statement->entries as $index => $entry) {
            if ($queue->contains($index)) {
                $queueRows[] = ['index' => $index, 'entry' => $entry];
            } else {
                $rows[] = ['entry' => $entry, 'match' => $matches[$index]];
            }
        }

        return view('pages.upload-review', [
            'statement' => $statement,
            'existing' => $existing,
            'queueRows' => $queueRows,
            'rows' => $rows,
            'queueState' => $queue->state(),
            'open' => $queue->openCount(),
        ]);
    }

    /**
     * Assign a group to an entry of the review queue.
     */
    public function assign(AssignReviewEntryRequest $request): JsonResponse
    {
        $statement = $this->pending();

        if ($statement === null) {
            return response()->json(['redirect' => route('upload')], 409);
        }

        $queue = $this->reviewQueue($statement);
        $index = $this->queuedIndex($request->integer('entry'), $queue);

        $queue->pick($index, $request->string('group')->toString());
        $this->storeReviewQueue($queue);

        return response()->json($queue->state());
    }

    /**
     * Tick or untick "always use this group" for the merchant of a queue entry.
     */
    public function always(ToggleAlwaysRuleRequest $request): JsonResponse
    {
        $statement = $this->pending();

        if ($statement === null) {
            return response()->json(['redirect' => route('upload')], 409);
        }

        $queue = $this->reviewQueue($statement);
        $index = $this->queuedIndex($request->integer('entry'), $queue);

        if ($request->boolean('always') && $queue->groupFor($index) === null) {
            throw ValidationException::withMessages(['always' => 'Pick a group first.']);
        }

        $queue->setAlways($index, $request->boolean('always'));
        $this->storeReviewQueue($queue);

        return response()->json($queue->state());
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

        $queue = $this->reviewQueue($parsed);

        if (($open = $queue->openCount()) > 0) {
            return redirect()->route('upload.review')
                ->with('review_error', $open === 1 ? '1 entry still needs a group.' : "{$open} entries still need a group.");
        }

        DB::transaction(function () use ($parsed, $queue) {
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

            $ruleIds = [];

            foreach ($queue->always() as $key => $choice) {
                $rule = Rule::query()->updateOrCreate(
                    ['source' => RuleSource::Manual->value, 'field' => RuleField::Merchant->value, 'pattern' => $choice['merchant'], 'direction' => RuleDirection::Out->value],
                    ['priority' => Rule::MANUAL_PRIORITY, 'group_key' => $choice['group_key'], 'share_divisor' => 1, 'ignore' => false],
                );

                $ruleIds[$key] = $rule->id;
            }

            $statement->transactions()->createMany(array_map(fn (ParsedEntry $entry, RuleMatch $match) => [
                ...$entry->toArray(),
                'period' => $parsed->period,
                'group_key' => $match->groupKey,
                'share_divisor' => $match->shareDivisor,
                'ignored' => $match->ignored,
                'rule_id' => $match->ruleId,
            ], $parsed->entries, $queue->finalMatches($ruleIds)));
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

    private function reviewQueue(ParsedStatement $statement): ReviewQueue
    {
        return new ReviewQueue(
            $statement->entries,
            $this->pendingAssignments($statement),
            session(DiscardPendingStatementImport::SESSION_KEY.'.picks', []),
            session(DiscardPendingStatementImport::SESSION_KEY.'.always', []),
        );
    }

    /**
     * Write picks and "always" choices as whole arrays: normalized merchants may contain dots.
     */
    private function storeReviewQueue(ReviewQueue $queue): void
    {
        session([
            DiscardPendingStatementImport::SESSION_KEY.'.picks' => $queue->picks(),
            DiscardPendingStatementImport::SESSION_KEY.'.always' => $queue->always(),
        ]);
    }

    private function queuedIndex(int $index, ReviewQueue $queue): int
    {
        if (! $queue->contains($index)) {
            throw ValidationException::withMessages(['entry' => 'This entry cannot be assigned.']);
        }

        return $index;
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
