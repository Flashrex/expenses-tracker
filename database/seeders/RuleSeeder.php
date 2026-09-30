<?php

namespace Database\Seeders;

use App\Enums\RuleDirection;
use App\Enums\RuleField;
use App\Enums\RuleSource;
use App\Models\Rule;
use App\Services\Groups\GroupCatalog;
use App\Services\Rules\TextNormalizer;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class RuleSeeder extends Seeder
{
    /**
     * Sync the seeded rules with config/expenses.php. Manual rules are never touched.
     */
    public function run(): void
    {
        $rows = config('expenses.rules');

        $keys = $this->validate($rows);

        foreach ($rows as $row) {
            Rule::query()->updateOrCreate([
                'source' => RuleSource::Seeded->value,
                'field' => $row['field'],
                'pattern' => $row['pattern'],
                'direction' => $row['direction'],
            ], [
                'priority' => $row['priority'] ?? 100,
                'group_key' => $row['group_key'] ?? null,
                'share_divisor' => $row['share_divisor'] ?? 1,
                'ignore' => $row['ignore'] ?? false,
            ]);
        }

        Rule::query()
            ->where('source', RuleSource::Seeded->value)
            ->get()
            ->reject(fn (Rule $rule) => in_array($this->key($rule->field->value, $rule->pattern, $rule->direction->value), $keys, true))
            ->each(fn (Rule $rule) => $rule->delete());
    }

    /**
     * Check every row before anything is written.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string> the key of each row
     *
     * @throws InvalidArgumentException
     */
    private function validate(array $rows): array
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

        return $keys;
    }

    private function key(string $field, string $pattern, string $direction): string
    {
        return $field."\0".$pattern."\0".$direction;
    }
}
