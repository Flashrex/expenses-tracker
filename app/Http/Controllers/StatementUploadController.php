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
use App\Services\Statements\ImportBatch;
use App\Services\Statements\IngStatementParser;
use App\Services\Statements\ParsedEntry;
use App\Services\Statements\ReviewQueue;
use App\Services\Statements\StatementParseException;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StatementUploadController extends Controller
{
    private const INVALID_FILE = 'Please choose a PDF file (max. 10 MB).';

    private const NOT_ING = "This doesn't look like an ING statement.";

    /**
     * Show the drop zone and the months imported so far.
     */
    public function create(Request $request): View
    {
        return view('pages.upload', [
            'imported' => Statement::query()->whereNotNull('confirmed_at')->orderByDesc('period')->pluck('period'),
            'tooLarge' => $request->boolean('too_large'),
            'maxUploadBytes' => (int) UploadedFile::getMaxFilesize(),
            'maxFiles' => 12,
        ]);
    }

    /**
     * Parse the uploaded PDFs and keep the valid months in the session for review, reporting the others.
     */
    public function store(StoreStatementUploadRequest $request, IngStatementParser $parser): RedirectResponse
    {
        $files = array_values(array_filter($request->file('statements', []), fn ($file) => $file instanceof UploadedFile));

        if ($files === []) {
            return redirect()->route('upload')->withErrors(['statements' => self::INVALID_FILE]);
        }

        $matcher = RuleMatcher::fromDatabase();
        $kept = [];
        $failed = [];
        $reasons = [];

        foreach ($files as $file) {
            $reason = $this->rejectReason($file);

            if ($reason === null) {
                try {
                    // The PDF is only read from its temp file, which PHP removes after the request.
                    $parsed = $parser->parse($file->getRealPath());

                    if (isset($kept[$parsed->period])) {
                        $reason = 'duplicate of '.Period::label($parsed->period);
                    } else {
                        $kept[$parsed->period] = ['statement' => $parsed, 'assignments' => $matcher->matchAll($parsed->entries)];
                    }
                } catch (StatementParseException) {
                    $reason = "doesn't look like an ING statement";
                }
            }

            if ($reason !== null) {
                $reasons[] = $reason;
                $failed[] = $file->getClientOriginalName().' – '.$reason;
            }
        }

        if ($kept === []) {
            if (count($files) === 1) {
                return redirect()->route('upload')->withErrors([
                    'statements' => $reasons[0] === "doesn't look like an ING statement" ? self::NOT_ING : self::INVALID_FILE,
                ]);
            }

            return redirect()->route('upload')->withErrors([
                'statements' => 'None of the files could be imported.',
                'files' => $failed,
            ]);
        }

        $batch = ImportBatch::start(array_values($kept), $failed);
        $this->saveBatch($batch);

        return redirect()->route('upload.review', $batch->nextPending());
    }

    /**
     * Show one month of the pending import.
     */
    public function review(?string $period = null): View|RedirectResponse
    {
        $batch = $this->batch();

        if ($batch === null) {
            return redirect()->route('upload');
        }

        if ($period === null || ! $batch->isPending($period)) {
            $next = $batch->nextPending();

            if ($next === null) {
                session()->forget(DiscardPendingStatementImport::SESSION_KEY);

                return redirect()->route('upload');
            }

            return redirect()->route('upload.review', $next);
        }

        $statement = $batch->statement($period);

        $existing = Statement::query()
            ->where('number', $statement->number)
            ->where('period', $statement->period)
            ->exists();

        $queue = $batch->reviewQueue($period);
        $matches = $batch->assignments($period);
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
            'period' => $period,
            'isBatch' => $batch->isBatch(),
            'steps' => $batch->steps(),
            'failed' => $batch->showsNotice() ? $batch->failed() : [],
        ]);
    }

    /**
     * Assign a group to an entry of the month's review queue.
     */
    public function assign(AssignReviewEntryRequest $request, string $period): JsonResponse
    {
        $batch = $this->batch();

        if ($batch === null) {
            return response()->json(['redirect' => route('upload')], 409);
        }

        if (! $batch->isPending($period)) {
            return response()->json(['redirect' => route('upload.review')], 409);
        }

        $queue = $batch->reviewQueue($period);
        $index = $this->queuedIndex($request->integer('entry'), $queue);

        $queue->pick($index, $request->string('group')->toString());
        $batch->storeReviewQueue($period, $queue);
        $this->saveBatch($batch);

        return response()->json($queue->state());
    }

    /**
     * Tick or untick "always use this group" for the merchant of a queue entry.
     */
    public function always(ToggleAlwaysRuleRequest $request, string $period): JsonResponse
    {
        $batch = $this->batch();

        if ($batch === null) {
            return response()->json(['redirect' => route('upload')], 409);
        }

        if (! $batch->isPending($period)) {
            return response()->json(['redirect' => route('upload.review')], 409);
        }

        $queue = $batch->reviewQueue($period);
        $index = $this->queuedIndex($request->integer('entry'), $queue);

        if ($request->boolean('always') && $queue->groupFor($index) === null) {
            throw ValidationException::withMessages(['always' => 'Pick a group first.']);
        }

        $queue->setAlways($index, $request->boolean('always'));
        $batch->storeReviewQueue($period, $queue);
        $this->saveBatch($batch);

        return response()->json($queue->state());
    }

    /**
     * Store the month, replacing an earlier import of the same statement, then move on to the next month.
     */
    public function confirm(string $period): RedirectResponse
    {
        $batch = $this->batch();

        if ($batch === null) {
            return redirect()->route('upload');
        }

        if (! $batch->isPending($period)) {
            return redirect()->route('upload.review');
        }

        $parsed = $batch->statement($period);
        $queue = $batch->reviewQueue($period);

        if (($open = $queue->openCount()) > 0) {
            return redirect()->route('upload.review', $period)
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

        $batch->markConfirmed($period);
        // After the transaction, so the manual rules saved above reach the months still waiting.
        $batch->applyRules(RuleMatcher::fromDatabase());

        return $this->advance($batch, $period);
    }

    /**
     * Leave the month out of the import and move on to the next one.
     */
    public function skip(string $period): RedirectResponse
    {
        $batch = $this->batch();

        if ($batch === null) {
            return redirect()->route('upload');
        }

        if (! $batch->isPending($period)) {
            return redirect()->route('upload.review');
        }

        $batch->markSkipped($period);

        return $this->advance($batch, $period);
    }

    /**
     * Drop the months not confirmed yet.
     */
    public function discard(): RedirectResponse
    {
        $batch = $this->batch();

        if ($batch === null) {
            return redirect()->route('upload');
        }

        $summary = $batch->summary(discarding: true);
        session()->forget(DiscardPendingStatementImport::SESSION_KEY);

        return $this->toUpload($summary);
    }

    /**
     * Hide the failed-files notice for the rest of the batch.
     */
    public function dismissNotice(Request $request): JsonResponse|RedirectResponse|Response
    {
        $batch = $this->batch();

        if ($batch === null) {
            return $request->expectsJson()
                ? response()->json(['redirect' => route('upload')], 409)
                : redirect()->route('upload');
        }

        $batch->dismissNotice();
        $this->saveBatch($batch);

        return $request->expectsJson()
            ? response()->noContent()
            : redirect()->back(fallback: route('upload.review'));
    }

    /**
     * Why a file is skipped before parsing, or null when it may be parsed.
     */
    private function rejectReason(UploadedFile $file): ?string
    {
        $validator = Validator::make(['file' => $file], ['file' => ['file', 'mimes:pdf', 'max:10240']]);

        if ($validator->passes()) {
            return null;
        }

        $failed = $validator->failed()['file'] ?? [];

        return match (true) {
            isset($failed['File']) || isset($failed['Uploaded']) => "couldn't be uploaded",
            isset($failed['Mimes']) => 'not a PDF',
            isset($failed['Max']) => 'larger than 10 MB',
            default => null,
        };
    }

    /**
     * Go to the next unfinished month, or end the batch on Upload with its summary.
     */
    private function advance(ImportBatch $batch, string $after): RedirectResponse
    {
        $next = $batch->nextPending($after);

        if ($next === null) {
            session()->forget(DiscardPendingStatementImport::SESSION_KEY);

            return $this->toUpload($batch->summary());
        }

        $this->saveBatch($batch);

        return redirect()->route('upload.review', $next);
    }

    private function toUpload(?string $status): RedirectResponse
    {
        $redirect = redirect()->route('upload');

        return $status === null ? $redirect : $redirect->with('status', $status);
    }

    private function batch(): ?ImportBatch
    {
        $data = session(DiscardPendingStatementImport::SESSION_KEY);

        return is_array($data) && isset($data['months']) ? ImportBatch::fromArray($data) : null;
    }

    /**
     * Written as a whole array: normalized merchants in "always" choices may contain dots.
     */
    private function saveBatch(ImportBatch $batch): void
    {
        session([DiscardPendingStatementImport::SESSION_KEY => $batch->toArray()]);
    }

    private function queuedIndex(int $index, ReviewQueue $queue): int
    {
        if (! $queue->contains($index)) {
            throw ValidationException::withMessages(['entry' => 'This entry cannot be assigned.']);
        }

        return $index;
    }
}
