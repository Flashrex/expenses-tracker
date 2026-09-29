<?php

namespace App\Services\Reports;

/**
 * One page of the entries card, with the numbers for its count and pagination.
 */
final readonly class EntryPage
{
    public const PER_PAGE = 50;

    /**
     * @param  list<EntryRow>  $rows
     */
    public function __construct(public array $rows, public int $total, public int $page) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / self::PER_PAGE));
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : ($this->page - 1) * self::PER_PAGE + 1;
    }

    public function to(): int
    {
        return min($this->page * self::PER_PAGE, $this->total);
    }

    /**
     * First, last and the pages around the current one; null marks a gap of more than one page.
     *
     * @return list<int|null>
     */
    public function window(): array
    {
        $last = $this->lastPage();
        $pages = array_unique(array_filter(
            [1, $last, $this->page - 1, $this->page, $this->page + 1],
            fn (int $page) => $page >= 1 && $page <= $last,
        ));
        sort($pages);

        foreach ($pages as $page) {
            if (in_array($page + 2, $pages, true) && ! in_array($page + 1, $pages, true)) {
                $pages[] = $page + 1;
            }
        }
        sort($pages);

        $window = [];

        foreach ($pages as $i => $page) {
            if ($i > 0 && $page - $pages[$i - 1] > 1) {
                $window[] = null;
            }
            $window[] = $page;
        }

        return $window;
    }
}
