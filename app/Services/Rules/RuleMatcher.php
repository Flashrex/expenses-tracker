<?php

namespace App\Services\Rules;

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

        // Higher priority first, then the more specific (longer) pattern, then the older rule.
        usort($this->rules, fn (array $a, array $b) => [$b['rule']->priority, mb_strlen($b['pattern']), (string) $a['rule']->id]
            <=> [$a['rule']->priority, mb_strlen($a['pattern']), (string) $b['rule']->id]);
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
