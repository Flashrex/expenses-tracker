<?php

namespace App\Models;

use App\Enums\RuleDirection;
use App\Enums\RuleField;
use App\Enums\RuleSource;
use Database\Factories\RuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property RuleField $field
 * @property string $pattern
 * @property RuleDirection $direction
 * @property int $priority
 * @property ?string $group_key
 * @property int $share_divisor
 * @property bool $ignore
 * @property RuleSource $source
 */
#[Fillable(['field', 'pattern', 'direction', 'priority', 'group_key', 'share_divisor', 'ignore', 'source'])]
class Rule extends Model
{
    /** @use HasFactory<RuleFactory> */
    use HasFactory;

    /**
     * Priority of rules the user creates while reviewing; it only orders manual rules among themselves, seeded rules always match first.
     */
    public const MANUAL_PRIORITY = 300;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 100,
        'group_key' => null,
        'share_divisor' => 1,
        'ignore' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => RuleField::class,
            'direction' => RuleDirection::class,
            'source' => RuleSource::class,
            'priority' => 'integer',
            'share_divisor' => 'integer',
            'ignore' => 'boolean',
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
}
