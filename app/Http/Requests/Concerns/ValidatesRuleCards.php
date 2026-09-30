<?php

namespace App\Http\Requests\Concerns;

use App\Enums\RuleDirection;
use App\Enums\RuleField;
use App\Enums\RuleOperator;
use App\Services\Rules\Condition;
use App\Services\Rules\TextNormalizer;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation of the rules of a card on the Groups & rules page.
 */
trait ValidatesRuleCards
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function ruleRules(bool $withShare): array
    {
        return [
            'rules' => ['present', 'array'],
            'rules.*.id' => ['nullable', 'string'],
            'rules.*.direction' => ['required', Rule::enum(RuleDirection::class)],
            ...($withShare ? ['rules.*.share' => ['required', 'integer', 'between:1,10']] : []),
            'rules.*.conditions' => ['required', 'array', 'min:1'],
            'rules.*.conditions.*.field' => ['required', Rule::enum(RuleField::class)],
            'rules.*.conditions.*.operator' => ['required', 'string'],
            'rules.*.conditions.*.value' => ['present', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function ruleMessages(): array
    {
        return [
            'rules.*.direction.*' => 'Pick a direction.',
            'rules.*.share.*' => 'Use a whole number from 1 to 10.',
            'rules.*.conditions.required' => 'Add at least one condition.',
            'rules.*.conditions.min' => 'Add at least one condition.',
        ];
    }

    /**
     * Checks that depend on the field of each condition: its operators and its kind of value.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('rules', []) as $i => $rule) {
                foreach ((array) ($rule['conditions'] ?? []) as $j => $condition) {
                    $field = is_array($condition) ? RuleField::tryFrom((string) ($condition['field'] ?? '')) : null;

                    if ($field === null) {
                        continue;
                    }

                    $path = "rules.{$i}.conditions.{$j}";
                    $operator = RuleOperator::tryFrom((string) ($condition['operator'] ?? ''));

                    if ($operator === null || ! in_array($operator, RuleOperator::forField($field), true)) {
                        $validator->errors()->add("{$path}.operator", 'Pick an operator.');
                    }

                    $value = $condition['value'] ?? null;

                    if ($value !== null && ! is_string($value)) {
                        continue;
                    }

                    if ($field->isAmount()) {
                        if (Condition::parseAmount((string) $value) === null) {
                            $validator->errors()->add("{$path}.value", 'Enter an amount above 0 with at most 2 decimals.');
                        }
                    } elseif (TextNormalizer::normalize($value) === '') {
                        $validator->errors()->add("{$path}.value", 'Enter a value.');
                    } elseif (mb_strlen($value) > 100) {
                        $validator->errors()->add("{$path}.value", 'Use at most 100 characters.');
                    }
                }
            }
        }];
    }
}
