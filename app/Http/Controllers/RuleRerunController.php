<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideRerunRequest;
use App\Models\Rule;
use App\Models\Transaction;
use App\Services\Rules\RuleMatcher;
use App\Services\Rules\RuleRerun;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class RuleRerunController extends Controller
{
    /**
     * Session key of the pending rerun: ['proposals' => transaction id => proposal, 'decisions' => transaction id => 'accept'|'decline'].
     */
    public const SESSION_KEY = 'rule_rerun';

    /**
     * Run the saved rules over every imported entry and open the preview, or report that nothing would change.
     */
    public function store(RuleRerun $rerun): RedirectResponse
    {
        $proposals = $rerun->propose(RuleMatcher::fromDatabase());

        if ($proposals === []) {
            session()->forget(self::SESSION_KEY);

            return redirect()->route('groups')->notify('All entries already match your rules.');
        }

        session([self::SESSION_KEY => ['proposals' => $proposals, 'decisions' => []]]);

        return redirect()->route('groups.rerun');
    }

    /**
     * List the proposed changes by month, newest first.
     */
    public function show(): View|RedirectResponse
    {
        $rerun = $this->pending();

        if ($rerun === null) {
            return redirect()->route('groups');
        }

        $proposals = $rerun['proposals'];
        $rules = Rule::query()->whereKey(array_values(array_unique(array_column($proposals, 'rule_id'))))->get()
            ->keyBy(fn (Rule $rule) => (string) $rule->id);

        $months = Transaction::query()->whereKey(array_keys($proposals))->get()
            ->sortBy([
                fn (Transaction $a, Transaction $b) => $b->period <=> $a->period,
                fn (Transaction $a, Transaction $b) => $b->booked_on <=> $a->booked_on,
                fn (Transaction $a, Transaction $b) => (string) $b->id <=> (string) $a->id,
            ])
            ->map(function (Transaction $transaction) use ($proposals, $rules) {
                $proposal = $proposals[(string) $transaction->id];

                return [
                    'id' => (string) $transaction->id,
                    'period' => $transaction->period,
                    'bookedShort' => $transaction->booked_on->format('d.m.'),
                    'bookedOn' => $transaction->booked_on->format('d.m.Y'),
                    'valueOn' => $transaction->value_on->format('d.m.Y'),
                    'type' => (string) $transaction->type,
                    'counterparty' => $transaction->counterparty === '' ? null : $transaction->counterparty,
                    'purpose' => (string) $transaction->purpose,
                    'description' => $transaction->description,
                    'merchant' => (string) $transaction->merchant,
                    'amountCents' => $transaction->amount_cents,
                    'current' => $transaction->result(),
                    'proposed' => ['group_key' => $proposal['group_key'], 'share_divisor' => $proposal['share_divisor'], 'ignored' => $proposal['ignored']],
                    'ruleText' => $rules->get($proposal['rule_id'])?->description() ?? 'Rule (since removed)',
                ];
            })
            ->groupBy('period')
            ->all();

        $ids = array_merge(...array_map(fn ($rows) => $rows->pluck('id')->all(), array_values($months)));
        $decisions = array_intersect_key($rerun['decisions'], array_flip($ids));

        return view('pages.groups-rerun', [
            'months' => $months,
            'ids' => $ids,
            'decisions' => $decisions,
            'undecided' => count($ids) - count($decisions),
        ]);
    }

    /**
     * Accept or decline one or more proposed changes.
     */
    public function decide(DecideRerunRequest $request): JsonResponse
    {
        $rerun = $this->pending();

        if ($rerun === null) {
            return response()->json(['redirect' => route('groups')], 409);
        }

        foreach ($request->input('entries') as $id) {
            if (isset($rerun['proposals'][$id])) {
                $rerun['decisions'][$id] = $request->string('decision')->toString();
            }
        }

        session([self::SESSION_KEY => $rerun]);

        return response()->json([
            'decisions' => (object) $rerun['decisions'],
            'undecided' => count($rerun['proposals']) - count($rerun['decisions']),
        ]);
    }

    /**
     * Apply every decision once all changes are decided.
     */
    public function apply(RuleRerun $rerun): RedirectResponse
    {
        $pending = $this->pending();

        if ($pending === null) {
            return redirect()->route('groups');
        }

        if (count($pending['decisions']) < count($pending['proposals'])) {
            return redirect()->route('groups.rerun')->with('rerun_error', 'Decide every change first.');
        }

        $updated = $rerun->apply($pending['proposals'], $pending['decisions']);
        session()->forget(self::SESSION_KEY);

        return redirect()->route('groups')->notify($updated === 1 ? 'Updated 1 entry.' : "Updated {$updated} entries.");
    }

    /**
     * Drop the proposed changes without changing anything.
     */
    public function cancel(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('groups');
    }

    /**
     * @return array{proposals: array<string, array{group_key: ?string, share_divisor: int, ignored: bool, rule_id: string}>, decisions: array<string, 'accept'|'decline'>}|null
     */
    private function pending(): ?array
    {
        $rerun = session(self::SESSION_KEY);

        return is_array($rerun) && isset($rerun['proposals']) ? $rerun : null;
    }
}
