<?php

namespace Database\Factories;

use App\Enums\RuleDirection;
use App\Enums\RuleField;
use App\Enums\RuleSource;
use App\Models\Rule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rule>
 */
class RuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'field' => RuleField::Merchant,
            'pattern' => 'TEGUT',
            'direction' => RuleDirection::Out,
            'priority' => 100,
            'group_key' => 'groceries',
            'share_divisor' => 1,
            'ignore' => false,
            'source' => RuleSource::Seeded,
        ];
    }

    /**
     * A rule added by the user.
     */
    public function manual(): static
    {
        return $this->state(fn () => ['source' => RuleSource::Manual]);
    }

    /**
     * A rule that marks entries as ignored.
     */
    public function ignoring(): static
    {
        return $this->state(fn () => ['group_key' => null, 'ignore' => true]);
    }
}
