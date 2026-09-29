<?php

namespace App\Services\Statements;

use App\Services\Rules\RuleMatch;
use App\Services\Rules\TextNormalizer;
use InvalidArgumentException;
use LogicException;

/**
 * The outgoing entries of a pending import that no rule matched, with the user's picks
 * and "always use this group" choices per merchant.
 */
final class ReviewQueue
{
    /** @var list<int> */
    private array $queue = [];

    /** @var array<int, string> */
    private array $picks = [];

    /** @var array<string, array{merchant: string, group_key: string}> */
    private array $always = [];

    /**
     * @param  list<ParsedEntry>  $entries
     * @param  list<RuleMatch>  $matches  same length and order as $entries
     * @param  array<int, string>  $picks  entry index => group key, hand picks
     * @param  array<string, array{merchant: string, group_key: string}>  $always  normalized merchant => choice
     */
    public function __construct(
        private readonly array $entries,
        private readonly array $matches,
        array $picks = [],
        array $always = [],
    ) {
        foreach ($this->entries as $i => $entry) {
            if ($this->matches[$i]->state($entry->direction()) === 'unassigned') {
                $this->queue[] = $i;
            }
        }

        $keys = array_map($this->key(...), $this->queue);

        foreach ($picks as $i => $group) {
            if ($this->contains((int) $i)) {
                $this->picks[(int) $i] = $group;
            }
        }

        foreach ($always as $key => $choice) {
            if (in_array((string) $key, $keys, true)) {
                $this->always[(string) $key] = $choice;
            }
        }
    }

    /**
     * @return list<int> indexes of the entries no rule matched, ascending
     */
    public function queue(): array
    {
        return $this->queue;
    }

    public function contains(int $index): bool
    {
        return in_array($index, $this->queue, true);
    }

    public function groupFor(int $index): ?string
    {
        return $this->picks[$index] ?? $this->always[$this->key($index)]['group_key'] ?? null;
    }

    /**
     * Whether the entry follows its merchant's "always" choice.
     */
    public function isAlways(int $index): bool
    {
        $key = $this->key($index);

        return isset($this->always[$key]) && $this->groupFor($index) === $this->always[$key]['group_key'];
    }

    /**
     * Queue entries that still have no group.
     */
    public function openCount(): int
    {
        return count(array_filter($this->queue, fn (int $i) => $this->groupFor($i) === null));
    }

    public function pick(int $index, string $groupKey): void
    {
        $this->ensureQueued($index);

        if ($this->isAlways($index)) {
            $this->always[$this->key($index)]['group_key'] = $groupKey;
        }

        $this->picks[$index] = $groupKey;
    }

    public function setAlways(int $index, bool $on): void
    {
        $this->ensureQueued($index);

        $group = $this->groupFor($index);

        if ($on) {
            if ($group === null) {
                throw new LogicException("Entry {$index} has no group yet.");
            }

            $this->picks[$index] = $group;
            $this->always[$this->key($index)] = ['merchant' => $this->entries[$index]->merchant, 'group_key' => $group];

            return;
        }

        if (! $this->isAlways($index)) {
            return;
        }

        $this->picks[$index] = $group;
        unset($this->always[$this->key($index)]);
    }

    /**
     * @return array<int, string>
     */
    public function picks(): array
    {
        return $this->picks;
    }

    /**
     * @return array<string, array{merchant: string, group_key: string}>
     */
    public function always(): array
    {
        return $this->always;
    }

    /**
     * @return array{entries: array<int, array{group: ?string, always: bool}>, open: int}
     */
    public function state(): array
    {
        $entries = [];

        foreach ($this->queue as $i) {
            $entries[$i] = ['group' => $this->groupFor($i), 'always' => $this->isAlways($i)];
        }

        return ['entries' => $entries, 'open' => $this->openCount()];
    }

    /**
     * The rule results with the user's choices applied to the queue entries.
     *
     * @param  array<string, string>  $ruleIds  normalized merchant => id of the saved manual rule
     * @return list<RuleMatch>
     */
    public function finalMatches(array $ruleIds): array
    {
        $matches = $this->matches;

        foreach ($this->queue as $i) {
            $ruleId = $this->isAlways($i) ? ($ruleIds[$this->key($i)] ?? null) : null;
            $matches[$i] = new RuleMatch($this->groupFor($i), 1, false, $ruleId);
        }

        return $matches;
    }

    private function key(int $index): string
    {
        return TextNormalizer::normalize($this->entries[$index]->merchant);
    }

    private function ensureQueued(int $index): void
    {
        if (! $this->contains($index)) {
            throw new InvalidArgumentException("Entry {$index} is not in the review queue.");
        }
    }
}
