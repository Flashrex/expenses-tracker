<?php

namespace App\Services\Rules;

use App\Enums\RuleSource;
use App\Models\Rule;
use App\Services\Statements\ParsedEntry;

final class RuleMatcher
{
    /** @var list<array{rule: Rule, pattern: string}> */
    private array $rules = [];

    /**
     * @param  iterable<Rule>  $rules
     */
    public function __construct(iterable $rules)
    {
        foreach ($rules as $rule) {
            $pattern = TextNormalizer::normalize($rule->pattern);

            if ($pattern !== '') {
                $this->rules[] = ['rule' => $rule, 'pattern' => $pattern];
            }
        }

        // Seeded rules before manual ones, then higher priority, then the more specific (longer) pattern, then the older rule.
        usort($this->rules, fn (array $a, array $b) => [self::isSeeded($b['rule']), $b['rule']->priority, mb_strlen($b['pattern']), (string) $a['rule']->id]
            <=> [self::isSeeded($a['rule']), $a['rule']->priority, mb_strlen($a['pattern']), (string) $b['rule']->id]);
    }

    private static function isSeeded(Rule $rule): bool
    {
        return $rule->source !== RuleSource::Manual;
    }

    public static function fromDatabase(): self
    {
        return new self(Rule::query()->get());
    }

    public function match(ParsedEntry $entry): RuleMatch
    {
        $direction = $entry->direction();
        $normalized = [];

        foreach ($this->rules as ['rule' => $rule, 'pattern' => $pattern]) {
            if (! $rule->direction->matches($direction)) {
                continue;
            }

            $text = $normalized[$rule->field->value] ??= TextNormalizer::normalize($rule->field->valueOf($entry));

            if (str_contains($text, $pattern)) {
                return RuleMatch::fromRule($rule);
            }
        }

        return RuleMatch::none();
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
