<?php

namespace App\Services\Groups;

use App\Models\Group;

/**
 * The groups in their order, loaded once per request.
 */
final class GroupCatalog
{
    /** @var array<string, array{name: string, color: string, sort: int}>|null */
    private ?array $groups = null;

    /**
     * @return array<string, array{name: string, color: string, sort: int}> key => group, in group order; sort = 1-based rank
     */
    public function all(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $this->groups = [];

        foreach (Group::query()->orderBy('position')->orderBy('_id')->get() as $index => $group) {
            $this->groups[$group->key] = ['name' => $group->name, 'color' => $group->color, 'sort' => $index + 1];
        }

        return $this->groups;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array{name: string, color: string, sort: int}|null
     */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Drop the loaded groups so the next call reads them again.
     */
    public function forget(): void
    {
        $this->groups = null;
    }
}
