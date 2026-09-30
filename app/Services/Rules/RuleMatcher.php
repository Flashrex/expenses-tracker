<?php

namespace App\Services\Rules;

use App\Enums\RuleSource;
use App\Models\Rule;
use App\Services\Groups\GroupCatalog;
use App\Services\Statements\ParsedEntry;

final class RuleMatcher
{
    /** @var list<Rule> */
    private array $rules = [];

    /**
     * Rules are checked in group order and by position inside a group, then the ignore rules by position,
     * then the "Always use" rules (longer value first, then the older rule).
     *
     * @param  iterable<Rule>  $rules
     * @param  list<string>  $groupKeys  group order (GroupCatalog::keys())
     */
    public function __construct(iterable $rules, array $groupKeys)
    {
        $groupRank = array_flip($groupKeys);
        $sortKeys = [];

        foreach ($rules as $rule) {
            if ($rule->conditionList() === []) {
                continue;
            }

            if (! $rule->ignore && ! isset($groupRank[$rule->group_key])) {
                continue;
            }

            $id = (string) $rule->id;

            $sortKeys[$id] = match (true) {
                $rule->source === RuleSource::Manual => [2, 0, -mb_strlen(TextNormalizer::normalize((string) $rule->conditions[0]['value'])), $id],
                $rule->ignore => [1, 0, $rule->position ?? PHP_INT_MAX, $id],
                default => [0, $groupRank[$rule->group_key], $rule->position ?? PHP_INT_MAX, $id],
            };

            $this->rules[] = $rule;
        }

        usort($this->rules, fn (Rule $a, Rule $b) => $sortKeys[(string) $a->id] <=> $sortKeys[(string) $b->id]);
    }

    public static function fromDatabase(): self
    {
        return new self(Rule::query()->get(), app(GroupCatalog::class)->keys());
    }

    public function match(ParsedEntry $entry): RuleMatch
    {
        $rule = $this->firstMatch($entry);

        return $rule === null ? RuleMatch::none() : RuleMatch::fromRule($rule);
    }

    /**
     * The first matching rule itself, for the rerun.
     */
    public function firstMatch(ParsedEntry $entry): ?Rule
    {
        foreach ($this->rules as $rule) {
            if ($rule->matches($entry)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param  list<ParsedEntry>  $entries
     * @return list<RuleMatch>
     */
    public function matchAll(array $entries): array
    {
        return array_map($this->match(...), $entries);
    }
}
