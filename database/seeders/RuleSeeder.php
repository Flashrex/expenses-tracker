<?php

namespace Database\Seeders;

use App\Enums\RuleDirection;
use App\Enums\RuleField;
use App\Enums\RuleOperator;
use App\Enums\RuleSource;
use App\Models\Group;
use App\Models\Rule;
use App\Services\Groups\GroupCatalog;
use App\Services\Rules\TextNormalizer;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class RuleSeeder extends Seeder
{
    /**
     * Seed the configured rules once into an empty rule set. Existing rules (edited on the Groups & rules page) are never touched.
     */
    public function run(): void
    {
        $rows = config('expenses.rules');

        $this->validate($rows);

        if (Rule::query()->where('source', RuleSource::System->value)->exists()) {
            return;
        }

        $positions = [];

        foreach ($rows as $row) {
            $groupKey = $row['group_key'] ?? null;
            $bucket = $groupKey ?? '';
            $positions[$bucket] = ($positions[$bucket] ?? 0) + 1;

            Rule::query()->create([
                'conditions' => [['field' => $row['field'], 'operator' => RuleOperator::Contains->value, 'value' => $row['pattern']]],
                'direction' => $row['direction'],
                'group_key' => $groupKey,
                'share_divisor' => $row['share_divisor'] ?? 1,
                'ignore' => $row['ignore'] ?? false,
                'source' => RuleSource::System,
                'position' => $positions[$bucket],
            ]);
        }
    }

    /**
     * Check every row before anything is written.
     *
     * @param  list<array<string, mixed>>  $rows
     *
     * @throws InvalidArgumentException
     */
    private function validate(array $rows): void
    {
        $groups = app(GroupCatalog::class)->keys();
        $keys = [];

        foreach ($rows as $index => $row) {
            $pattern = $row['pattern'] ?? null;
            $fail = fn (string $reason) => throw new InvalidArgumentException(
                "Rule #{$index} (".var_export($pattern, true)."): {$reason}."
            );

            if (RuleField::tryFrom($row['field'] ?? '') === null) {
                $fail('unknown field');
            }
            if (RuleDirection::tryFrom($row['direction'] ?? '') === null) {
                $fail('unknown direction');
            }
            if (! is_string($pattern) || TextNormalizer::normalize($pattern) === '') {
                $fail('pattern is empty');
            }

            $ignore = $row['ignore'] ?? false;
            $groupKey = $row['group_key'] ?? null;

            if ($ignore && $groupKey !== null) {
                $fail('an ignore rule cannot have a group');
            }
            if (! $ignore && $groupKey === null) {
                $fail('needs a group or ignore');
            }
            if ($groupKey !== null && ! in_array($groupKey, $groups, true)) {
                $fail("unknown group \"{$groupKey}\"");
            }
            if ($groupKey === Group::OTHER) {
                $fail('"other" takes the entries no rule matches and cannot have rules');
            }

            $divisor = $row['share_divisor'] ?? 1;

            if (! is_int($divisor) || $divisor < 1) {
                $fail('share_divisor must be an integer of at least 1');
            }

            $key = $this->key($row['field'], $pattern, $row['direction']);

            if (in_array($key, $keys, true)) {
                $fail('duplicate of an earlier rule');
            }

            $keys[] = $key;
        }

    }

    private function key(string $field, string $pattern, string $direction): string
    {
        return $field."\0".$pattern."\0".$direction;
    }
}
