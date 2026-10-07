<?php

namespace App\Services\Reports;

use App\Enums\EntryStatus;
use App\Services\Rules\TextNormalizer;

/**
 * Group, status, search and page of the Overview's entries card, as carried in the query string.
 */
final readonly class EntryFilters
{
    public const MAX_QUERY_LENGTH = 100;

    public function __construct(
        public ?string $group = null,
        public EntryStatus $status = EntryStatus::All,
        public ?string $search = null,
        public int $page = 1,
    ) {}

    /**
     * Filters from the query string, or null when any value is invalid.
     *
     * @param  array<string, mixed>  $query  $request->query()
     * @param  list<string>  $groupKeys  GroupCatalog::keys()
     */
    public static function fromQuery(array $query, array $groupKeys): ?self
    {
        foreach (['group', 'status', 'q', 'page'] as $key) {
            if (array_key_exists($key, $query) && ! is_string($query[$key])) {
                return null;
            }
        }

        $group = $query['group'] ?? null;

        if ($group !== null && ! in_array($group, $groupKeys, true)) {
            return null;
        }

        $status = isset($query['status']) ? EntryStatus::tryFrom($query['status']) : EntryStatus::All;

        if ($status === null) {
            return null;
        }

        $search = $query['q'] ?? null;

        if ($search !== null) {
            if (mb_strlen($search) > self::MAX_QUERY_LENGTH) {
                return null;
            }

            $search = trim($search);
            $search = TextNormalizer::normalize($search) === '' ? null : $search;
        }

        $page = $query['page'] ?? '1';

        if (! preg_match('/^[1-9]\d{0,5}$/', $page)) {
            return null;
        }

        return new self($group, $status, $search, (int) $page);
    }

    public function isFiltered(): bool
    {
        return $this->group !== null || $this->status !== EntryStatus::All || $this->search !== null;
    }

    public function normalizedSearch(): ?string
    {
        return $this->search === null ? null : TextNormalizer::normalize($this->search);
    }

    public function withGroup(?string $group): self
    {
        return new self($group, $this->status, $this->search);
    }

    public function withStatus(EntryStatus $status): self
    {
        return new self($this->group, $status, $this->search);
    }

    public function withPage(int $page): self
    {
        return new self($this->group, $this->status, $this->search, $page);
    }

    public function cleared(): self
    {
        return new self;
    }

    /**
     * Only the non-default values, so default filters leave no trace in URLs.
     *
     * @return array{group?: string, status?: string, q?: string, page?: int}
     */
    public function query(): array
    {
        return array_filter([
            'group' => $this->group,
            'status' => $this->status === EntryStatus::All ? null : $this->status->value,
            'q' => $this->search,
            'page' => $this->page === 1 ? null : $this->page,
        ], fn (mixed $value) => $value !== null);
    }
}
