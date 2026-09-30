<?php

namespace App\Models;

use App\Services\Groups\GroupCatalog;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string $color lowercase #rrggbb
 * @property int $position
 */
#[Fillable(['key', 'name', 'color', 'position'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * The group that cannot be deleted; entries of deleted groups move there by default.
     */
    public const OTHER = 'other';

    protected static function booted(): void
    {
        static::saved(fn () => app(GroupCatalog::class)->forget());
        static::deleted(fn () => app(GroupCatalog::class)->forget());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * Store a new group order.
     *
     * @param  list<string>  $keys  all group keys in the new order
     */
    public static function reorder(array $keys): void
    {
        foreach (array_values($keys) as $index => $key) {
            static::query()->where('key', $key)->update(['position' => $index + 1]);
        }

        app(GroupCatalog::class)->forget();
    }
}
