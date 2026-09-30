<?php

namespace App\Services\Groups;

use App\Enums\RuleDirection;
use App\Enums\RuleField;
use App\Enums\RuleSource;
use App\Models\Group;
use App\Models\Rule;
use App\Services\Rules\Condition;
use App\Services\Rules\TextNormalizer;
use Illuminate\Support\Collection;

/**
 * The group cards and the Ignored card of the Groups & rules page: what they show and how they are saved.
 */
final class GroupCards
{
    public function __construct(private GroupCatalog $catalog) {}

    /**
     * Payload of the Groups & rules page.
     *
     * @return array{cards: list<array<string, mixed>>, ignored: array{rules: list<array<string, mixed>>}}
     */
    public function state(): array
    {
        $groups = Group::query()->get()->keyBy('key');
        $rules = Rule::query()->get();

        $cards = [];

        foreach ($this->catalog->keys() as $key) {
            $cards[] = $this->cardFrom($groups[$key], $rules);
        }

        return ['cards' => $cards, 'ignored' => $this->ignoredFrom($rules)];
    }

    /**
     * One group card as in state()['cards'].
     *
     * @return array<string, mixed>
     */
    public function card(Group $group): array
    {
        return $this->cardFrom($group, Rule::query()->where('group_key', $group->key)->get());
    }

    /**
     * The Ignored card.
     *
     * @return array{rules: list<array<string, mixed>>}
     */
    public function ignoredCard(): array
    {
        return $this->ignoredFrom(Rule::query()->where('ignore', true)->get());
    }

    /**
     * Replace the system rules of one card with $rules; their order becomes the rule positions.
     * Call inside a transaction.
     *
     * @param  ?string  $groupKey  null = the Ignored card
     * @param  list<array{id?: ?string, direction: string, share?: int|string, conditions: list<array{field: string, operator: string, value: ?string}>}>  $rules
     *
     * @throws StaleCardException when an id is not a system rule of this card
     */
    public function saveRules(?string $groupKey, array $rules): void
    {
        $existing = Rule::query()
            ->where('source', RuleSource::System->value)
            ->when($groupKey === null, fn ($query) => $query->where('ignore', true), fn ($query) => $query->where('group_key', $groupKey)->where('ignore', false))
            ->get()
            ->keyBy(fn (Rule $rule) => (string) $rule->id);

        $kept = [];

        foreach (array_values($rules) as $index => $data) {
            $id = $data['id'] ?? null;

            if ($id !== null && ! $existing->has($id)) {
                throw new StaleCardException("Rule {$id} is not part of this card.");
            }

            $rule = $id !== null ? $existing[$id] : new Rule(['source' => RuleSource::System]);

            $rule->fill([
                'conditions' => array_map(fn (array $condition) => [
                    'field' => $condition['field'],
                    'operator' => $condition['operator'],
                    'value' => RuleField::from($condition['field'])->isAmount()
                        ? Condition::parseAmount((string) $condition['value'])
                        : (string) $condition['value'],
                ], array_values($data['conditions'])),
                'direction' => RuleDirection::from($data['direction']),
                'group_key' => $groupKey,
                'share_divisor' => $groupKey === null ? 1 : (int) $data['share'],
                'ignore' => $groupKey === null,
                'position' => $index + 1,
            ])->save();

            $kept[] = (string) $rule->id;
        }

        $existing->except($kept)->each(fn (Rule $rule) => $rule->delete());
    }

    /**
     * @param  Collection<int, Rule>  $rules
     * @return array<string, mixed>
     */
    private function cardFrom(Group $group, Collection $rules): array
    {
        $own = $rules->filter(fn (Rule $rule) => $rule->group_key === $group->key && ! $rule->ignore);

        return [
            'key' => $group->key,
            'name' => $group->name,
            'color' => $group->color,
            'isOther' => $group->key === Group::OTHER,
            'rules' => $this->ruleRows($own->filter(fn (Rule $rule) => $rule->source === RuleSource::System), withShare: true),
            'always' => $own
                ->filter(fn (Rule $rule) => $rule->source === RuleSource::Manual)
                ->sortBy([
                    fn (Rule $a, Rule $b) => $this->valueLength($b) <=> $this->valueLength($a),
                    fn (Rule $a, Rule $b) => (string) $a->id <=> (string) $b->id,
                ])
                ->map(fn (Rule $rule) => ['id' => (string) $rule->id, 'text' => $rule->conditionText()])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Rule>  $rules
     * @return array{rules: list<array<string, mixed>>}
     */
    private function ignoredFrom(Collection $rules): array
    {
        return [
            'rules' => $this->ruleRows($rules->filter(fn (Rule $rule) => $rule->ignore && $rule->source === RuleSource::System), withShare: false),
        ];
    }

    /**
     * @param  Collection<int, Rule>  $rules
     * @return list<array<string, mixed>>
     */
    private function ruleRows(Collection $rules, bool $withShare): array
    {
        return $rules
            ->sortBy([
                fn (Rule $a, Rule $b) => ($a->position ?? PHP_INT_MAX) <=> ($b->position ?? PHP_INT_MAX),
                fn (Rule $a, Rule $b) => (string) $a->id <=> (string) $b->id,
            ])
            ->map(fn (Rule $rule) => [
                'id' => (string) $rule->id,
                'direction' => $rule->direction->value,
                ...($withShare ? ['share' => $rule->share_divisor] : []),
                'conditions' => array_map(fn (Condition $condition) => [
                    'field' => $condition->field->value,
                    'operator' => $condition->operator->value,
                    'value' => $condition->inputValue(),
                ], $rule->conditionList()),
            ])
            ->values()
            ->all();
    }

    private function valueLength(Rule $rule): int
    {
        return mb_strlen(TextNormalizer::normalize((string) ($rule->conditions[0]['value'] ?? '')));
    }
}
