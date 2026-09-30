<?php

namespace App\Services\Statements;

use App\Services\Rules\RuleMatch;
use App\Services\Rules\RuleMatcher;
use App\Support\Period;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The parsed but unconfirmed months of an upload, reviewed one at a time, oldest first.
 * Serialised to the session; the controller loads and saves it.
 */
final class ImportBatch
{
    /**
     * @param  array<string, array<string, mixed>>  $months  period => parsed statement + assignments, picks, always, overrides, status
     * @param  list<string>  $failed  "‹file› – ‹reason›" lines, upload order
     */
    private function __construct(
        private array $months,
        private array $failed,
        private bool $noticeDismissed,
    ) {
        ksort($this->months);
    }

    /**
     * @param  list<array{statement: ParsedStatement, assignments: list<RuleMatch>}>  $parsed
     * @param  list<string>  $failed
     */
    public static function start(array $parsed, array $failed): self
    {
        $months = [];

        foreach ($parsed as ['statement' => $statement, 'assignments' => $assignments]) {
            $months[$statement->period] = $statement->toArray() + [
                'assignments' => array_map(fn (RuleMatch $match) => $match->toArray(), $assignments),
                'picks' => [],
                'always' => [],
                'overrides' => [],
                'status' => 'pending',
            ];
        }

        return new self($months, array_values($failed), false);
    }

    /**
     * @param  array{months?: array<string, array<string, mixed>>, failed?: list<string>, notice_dismissed?: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['months'] ?? [], $data['failed'] ?? [], (bool) ($data['notice_dismissed'] ?? false));
    }

    /**
     * @return array{months: array<string, array<string, mixed>>, failed: list<string>, notice_dismissed: bool}
     */
    public function toArray(): array
    {
        return [
            'months' => $this->months,
            'failed' => $this->failed,
            'notice_dismissed' => $this->noticeDismissed,
        ];
    }

    /**
     * @return list<string> ascending
     */
    public function periods(): array
    {
        return array_map('strval', array_keys($this->months));
    }

    /**
     * Whether more than one month is reviewed.
     */
    public function isBatch(): bool
    {
        return count($this->months) >= 2;
    }

    public function isPending(string $period): bool
    {
        return ($this->months[$period]['status'] ?? null) === 'pending';
    }

    public function statement(string $period): ParsedStatement
    {
        return ParsedStatement::fromArray($this->months[$period]);
    }

    /**
     * The rule results for the month, one per entry.
     *
     * @return list<RuleMatch>
     */
    public function assignments(string $period): array
    {
        $entries = $this->months[$period]['entries'];
        $assignments = $this->months[$period]['assignments'] ?? null;

        if (! is_array($assignments) || count($assignments) !== count($entries)) {
            return array_map(fn () => RuleMatch::none(), array_values($entries));
        }

        return array_map(RuleMatch::fromArray(...), array_values($assignments));
    }

    public function reviewQueue(string $period): ReviewQueue
    {
        return new ReviewQueue(
            $this->statement($period)->entries,
            $this->assignments($period),
            $this->months[$period]['picks'] ?? [],
            $this->months[$period]['always'] ?? [],
        );
    }

    public function storeReviewQueue(string $period, ReviewQueue $queue): void
    {
        $this->months[$period]['picks'] = $queue->picks();
        $this->months[$period]['always'] = $queue->always();
    }

    /**
     * Whether the entry is outgoing and a rule put it into a group, so its group may be changed by hand.
     */
    public function canOverride(string $period, int $index): bool
    {
        $entries = $this->statement($period)->entries;

        if (! isset($entries[$index]) || $entries[$index]->direction() !== 'out') {
            return false;
        }

        return $this->assignments($period)[$index]->state('out') === 'group';
    }

    /**
     * Entries moved away from their rule's group.
     *
     * @return array<int, string> entry index => group key, ascending
     */
    public function overrides(string $period): array
    {
        $assignments = $this->assignments($period);
        $overrides = [];

        foreach ($this->months[$period]['overrides'] ?? [] as $i => $group) {
            $i = (int) $i;

            if ($this->canOverride($period, $i) && $group !== $assignments[$i]->groupKey) {
                $overrides[$i] = $group;
            }
        }

        ksort($overrides);

        return $overrides;
    }

    /**
     * Put a rule-grouped entry into another group; picking the rule's own group removes the override.
     */
    public function override(string $period, int $index, string $groupKey): void
    {
        if (! $this->canOverride($period, $index)) {
            throw new InvalidArgumentException("Entry {$index} cannot be regrouped.");
        }

        $overrides = $this->overrides($period);

        if ($groupKey === $this->assignments($period)[$index]->groupKey) {
            unset($overrides[$index]);
        } else {
            $overrides[$index] = $groupKey;
        }

        $this->months[$period]['overrides'] = $overrides;
    }

    /**
     * The rule results with the queue choices and the overrides applied.
     *
     * @param  array<string, string>  $ruleIds  normalized merchant => id of the saved manual rule
     * @return list<RuleMatch>
     */
    public function finalMatches(string $period, array $ruleIds): array
    {
        $matches = $this->reviewQueue($period)->finalMatches($ruleIds);

        foreach ($this->overrides($period) as $i => $group) {
            $matches[$i] = new RuleMatch($group, 1, false, null);
        }

        return $matches;
    }

    public function markConfirmed(string $period): void
    {
        $this->months[$period]['status'] = 'confirmed';
    }

    public function markSkipped(string $period): void
    {
        $this->months[$period]['status'] = 'skipped';
    }

    /**
     * The first pending month after $after, else the first pending month overall.
     */
    public function nextPending(?string $after = null): ?string
    {
        $pending = array_values(array_filter($this->periods(), $this->isPending(...)));

        foreach ($pending as $period) {
            if ($after === null || $period > $after) {
                return $period;
            }
        }

        return $pending[0] ?? null;
    }

    /**
     * Match the still-open queue entries of every pending month again, e.g. after new manual rules were saved.
     * Entries that already have a group (hand pick or "always" choice) are left alone.
     */
    public function applyRules(RuleMatcher $matcher): void
    {
        foreach ($this->periods() as $period) {
            if (! $this->isPending($period)) {
                continue;
            }

            $entries = $this->statement($period)->entries;
            $queue = $this->reviewQueue($period);
            $assignments = array_map(fn (RuleMatch $match) => $match->toArray(), $this->assignments($period));

            foreach ($queue->queue() as $i) {
                if ($queue->groupFor($i) !== null) {
                    continue;
                }

                $match = $matcher->match($entries[$i]);

                if ($match->state($entries[$i]->direction()) !== 'unassigned') {
                    $assignments[$i] = $match->toArray();
                }
            }

            $this->months[$period]['assignments'] = $assignments;
        }
    }

    /**
     * @return list<array{period: string, label: string, state: 'open'|'ready'|'confirmed'|'skipped', open: int}>
     */
    public function steps(): array
    {
        return array_map(function (string $period) {
            $status = $this->months[$period]['status'];
            $open = $status === 'pending' ? $this->reviewQueue($period)->openCount() : 0;

            return [
                'period' => $period,
                'label' => Period::short($period),
                'state' => $status === 'pending' ? ($open > 0 ? 'open' : 'ready') : $status,
                'open' => $open,
            ];
        }, $this->periods());
    }

    /**
     * @return list<string>
     */
    public function failed(): array
    {
        return $this->failed;
    }

    public function showsNotice(): bool
    {
        return $this->failed !== [] && ! $this->noticeDismissed;
    }

    public function dismissNotice(): void
    {
        $this->noticeDismissed = true;
    }

    /**
     * The status message for the Upload page once the batch ends, or null for none.
     */
    public function summary(bool $discarding = false): ?string
    {
        $confirmed = $this->withStatus('confirmed');

        if (! $this->isBatch()) {
            if ($confirmed === []) {
                return null;
            }

            $period = array_key_first($confirmed);

            return Period::label((string) $period).' imported · '.count($confirmed[$period]['entries']).' entries';
        }

        $suffix = array_filter([
            ($skipped = count($this->withStatus('skipped'))) > 0 ? "{$skipped} skipped" : null,
            $discarding && ($pending = count($this->withStatus('pending'))) > 0 ? "{$pending} discarded" : null,
        ]);
        $suffix = $suffix === [] ? '' : ' ('.implode(', ', $suffix).')';

        if ($confirmed === []) {
            return $discarding ? null : 'Nothing imported'.$suffix;
        }

        $months = count($confirmed);
        $entries = array_sum(array_map(fn (array $month) => count($month['entries']), $confirmed));

        return $months.' '.Str::plural('month', $months).' imported · '.$entries.' '.Str::plural('entry', $entries).$suffix;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function withStatus(string $status): array
    {
        return array_filter($this->months, fn (array $month) => $month['status'] === $status);
    }
}
