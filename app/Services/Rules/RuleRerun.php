<?php

namespace App\Services\Rules;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Runs the saved rules over every imported entry and applies the changes the user accepted.
 */
final class RuleRerun
{
    /**
     * Entries whose first matching rule gives another group, share divisor or ignored state than they have now,
     * leaving out entries whose declined result is proposed again.
     *
     * @return array<string, array{group_key: ?string, share_divisor: int, ignored: bool, rule_id: string}> transaction id => proposal
     */
    public function propose(RuleMatcher $matcher): array
    {
        $proposals = [];

        foreach (Transaction::query()->cursor() as $transaction) {
            $rule = $matcher->firstMatch($transaction->toParsedEntry());

            if ($rule === null) {
                continue;
            }

            $match = RuleMatch::fromRule($rule);
            $result = ['group_key' => $match->groupKey, 'share_divisor' => $match->shareDivisor, 'ignored' => $match->ignored];

            if ($result === $transaction->result() || $result === $this->declined($transaction)) {
                continue;
            }

            $proposals[(string) $transaction->id] = [...$result, 'rule_id' => (string) $rule->id];
        }

        return $proposals;
    }

    /**
     * Accepted entries take the proposal; declined ones keep their group, become "picked manually" and remember the proposal.
     *
     * @param  array<string, array{group_key: ?string, share_divisor: int, ignored: bool, rule_id: string}>  $proposals
     * @param  array<string, 'accept'|'decline'>  $decisions
     * @return int number of accepted entries updated
     */
    public function apply(array $proposals, array $decisions): int
    {
        return DB::transaction(function () use ($proposals, $decisions) {
            $accepted = 0;
            $transactions = Transaction::query()->whereKey(array_keys($proposals))->get()->keyBy(fn (Transaction $transaction) => (string) $transaction->id);

            foreach ($proposals as $id => $proposal) {
                $transaction = $transactions[$id] ?? null;

                if ($transaction === null) {
                    continue;
                }

                if (($decisions[$id] ?? null) === 'accept') {
                    $transaction->update([...$proposal, 'declined' => null, 'unmatched' => false]);
                    $accepted++;
                } elseif (($decisions[$id] ?? null) === 'decline') {
                    $transaction->update([
                        'rule_id' => null,
                        'unmatched' => false,
                        'declined' => ['group_key' => $proposal['group_key'], 'share_divisor' => $proposal['share_divisor'], 'ignored' => $proposal['ignored']],
                    ]);
                }
            }

            return $accepted;
        });
    }

    /**
     * @return array{group_key: ?string, share_divisor: int, ignored: bool}|null
     */
    private function declined(Transaction $transaction): ?array
    {
        $declined = $transaction->declined;

        if (! is_array($declined)) {
            return null;
        }

        return ['group_key' => $declined['group_key'] ?? null, 'share_divisor' => (int) ($declined['share_divisor'] ?? 1), 'ignored' => (bool) ($declined['ignored'] ?? false)];
    }
}
