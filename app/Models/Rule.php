<?php

namespace App\Models;

use App\Enums\RuleDirection;
use App\Enums\RuleSource;
use App\Services\Rules\Condition;
use App\Services\Statements\ParsedEntry;
use Database\Factories\RuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property list<array{field: string, operator: string, value: string|int}> $conditions
 * @property RuleDirection $direction
 * @property ?string $group_key
 * @property int $share_divisor
 * @property bool $ignore
 * @property RuleSource $source
 * @property ?int $position order inside its card; null for "Always use" rules
 */
#[Fillable(['conditions', 'direction', 'group_key', 'share_divisor', 'ignore', 'source', 'position'])]
class Rule extends Model
{
    /** @use HasFactory<RuleFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'group_key' => null,
        'share_divisor' => 1,
        'ignore' => false,
        'position' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => RuleDirection::class,
            'source' => RuleSource::class,
            'share_divisor' => 'integer',
            'ignore' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * The transactions this rule was applied to.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return list<Condition>
     */
    public function conditionList(): array
    {
        return array_map(Condition::fromArray(...), $this->conditions ?? []);
    }

    /**
     * Whether the direction fits and every condition matches; a rule without conditions never matches.
     */
    public function matches(ParsedEntry $entry): bool
    {
        $conditions = $this->conditionList();

        if ($conditions === [] || ! $this->direction->matches($entry->direction())) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (! $condition->matches($entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * How the rule reads in entry details: `Rule · merchant contains "xy" AND amount is greater than 50,00 €`.
     */
    public function description(): string
    {
        return ($this->source === RuleSource::Manual ? 'Manual rule' : 'Rule').' · '.$this->conditionText();
    }

    /**
     * The conditions alone, joined by AND.
     */
    public function conditionText(): string
    {
        return implode(' AND ', array_map(fn (Condition $condition) => $condition->describe(), $this->conditionList()));
    }
}
