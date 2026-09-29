<?php

namespace App\Services\Reports;

use App\Enums\ReportMode;
use App\Support\Period;

/**
 * A reported month ("2026-06") or year ("2026"), with navigation limited to imported months.
 */
final readonly class ReportPeriod
{
    private function __construct(public ReportMode $mode, public string $value) {}

    public static function month(string $period): self
    {
        return new self(ReportMode::Month, $period);
    }

    public static function year(string $year): self
    {
        return new self(ReportMode::Year, $year);
    }

    /**
     * The requested period, the latest imported month when nothing is requested, or null when the request is invalid.
     *
     * @param  list<string>  $imported  imported months (YYYY-MM), ascending, unique, non-empty
     */
    public static function resolve(mixed $month, mixed $year, array $imported): ?self
    {
        if ($month !== null) {
            return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) && in_array($month, $imported, true)
                ? self::month($month)
                : null;
        }

        if ($year !== null) {
            return is_string($year) && preg_match('/^\d{4}$/', $year) && self::year($year)->hasData($imported)
                ? self::year($year)
                : null;
        }

        return self::month($imported[array_key_last($imported)]);
    }

    public function isMonth(): bool
    {
        return $this->mode === ReportMode::Month;
    }

    /** First month of the range (YYYY-MM). */
    public function from(): string
    {
        return $this->isMonth() ? $this->value : "{$this->value}-01";
    }

    /** Last month of the range (YYYY-MM). */
    public function to(): string
    {
        return $this->isMonth() ? $this->value : "{$this->value}-12";
    }

    public function label(): string
    {
        return $this->isMonth() ? Period::label($this->value) : $this->value;
    }

    /** The previous calendar period, whether imported or not. */
    public function previous(): self
    {
        if (! $this->isMonth()) {
            return self::year((string) ((int) $this->value - 1));
        }

        [$year, $month] = array_map('intval', explode('-', $this->value));

        return $month === 1
            ? self::month(sprintf('%04d-%02d', $year - 1, 12))
            : self::month(sprintf('%04d-%02d', $year, $month - 1));
    }

    /**
     * @param  list<string>  $imported
     */
    public function hasData(array $imported): bool
    {
        if ($this->isMonth()) {
            return in_array($this->value, $imported, true);
        }

        return array_any($imported, fn (string $month) => str_starts_with($month, "{$this->value}-"));
    }

    /**
     * The nearest earlier imported month or year.
     *
     * @param  list<string>  $imported
     */
    public function earlier(array $imported): ?self
    {
        $candidates = array_filter($this->candidates($imported), fn (string $value) => $value < $this->value);

        return $candidates === [] ? null : $this->withValue(max($candidates));
    }

    /**
     * The nearest later imported month or year.
     *
     * @param  list<string>  $imported
     */
    public function later(array $imported): ?self
    {
        $candidates = array_filter($this->candidates($imported), fn (string $value) => $value > $this->value);

        return $candidates === [] ? null : $this->withValue(min($candidates));
    }

    /**
     * Month → its year; year → the latest imported month of that year.
     *
     * @param  list<string>  $imported
     */
    public function toggled(array $imported): self
    {
        if ($this->isMonth()) {
            return self::year(substr($this->value, 0, 4));
        }

        $months = array_filter($imported, fn (string $month) => str_starts_with($month, "{$this->value}-"));

        return self::month(max($months));
    }

    /** @return array{month: string}|array{year: string} */
    public function query(): array
    {
        return [$this->mode->value => $this->value];
    }

    /**
     * @param  list<string>  $imported
     * @return list<string>
     */
    private function candidates(array $imported): array
    {
        return $this->isMonth()
            ? $imported
            : array_values(array_unique(array_map(fn (string $month) => substr($month, 0, 4), $imported)));
    }

    private function withValue(string $value): self
    {
        return new self($this->mode, $value);
    }
}
