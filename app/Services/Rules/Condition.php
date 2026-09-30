<?php

namespace App\Services\Rules;

use App\Enums\RuleField;
use App\Enums\RuleOperator;
use App\Services\Statements\ParsedEntry;
use App\Support\Money;

/**
 * One condition of a rule: a field, an operator and a value (text, or cents for the amount).
 */
final readonly class Condition
{
    public function __construct(
        public RuleField $field,
        public RuleOperator $operator,
        public string|int $value,
    ) {}

    /**
     * @param  array{field: string, operator: string, value: string|int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(RuleField::from($data['field']), RuleOperator::from($data['operator']), $data['value']);
    }

    /**
     * @return array{field: string, operator: string, value: string|int}
     */
    public function toArray(): array
    {
        return ['field' => $this->field->value, 'operator' => $this->operator->value, 'value' => $this->value];
    }

    /**
     * Text is compared normalized (case, spaces, umlauts); the amount without its sign.
     */
    public function matches(ParsedEntry $entry): bool
    {
        if ($this->field->isAmount()) {
            $cents = abs($entry->amountCents);
            $value = (int) $this->value;

            return match ($this->operator) {
                RuleOperator::Equals => $cents === $value,
                RuleOperator::NotEquals => $cents !== $value,
                RuleOperator::GreaterThan => $cents > $value,
                RuleOperator::AtLeast => $cents >= $value,
                RuleOperator::LessThan => $cents < $value,
                RuleOperator::AtMost => $cents <= $value,
                default => false,
            };
        }

        $text = TextNormalizer::normalize($this->field->valueOf($entry));
        $needle = TextNormalizer::normalize((string) $this->value);

        return match ($this->operator) {
            RuleOperator::Contains => str_contains($text, $needle),
            RuleOperator::NotContains => ! str_contains($text, $needle),
            RuleOperator::Equals => $text === $needle,
            default => false,
        };
    }

    /**
     * How the condition reads: `merchant contains "Spotify"` or `amount is greater than 50,00 €`.
     */
    public function describe(): string
    {
        $value = $this->field->isAmount() ? Money::format((int) $this->value) : '"'.$this->value.'"';

        return $this->field->value.' '.$this->operator->label().' '.$value;
    }

    /**
     * The value as the input field shows it: text as stored, the amount as "20,00".
     */
    public function inputValue(): string
    {
        return $this->field->isAmount()
            ? number_format((int) $this->value / 100, 2, ',', '')
            : (string) $this->value;
    }

    /**
     * Cents of a typed euro amount ("12,5", "12.50", "20"), or null when it is not above 0 with at most 2 decimals.
     */
    public static function parseAmount(string $input): ?int
    {
        if (preg_match('/^(\d+)(?:[.,](\d{1,2}))?$/', $input, $parts) !== 1) {
            return null;
        }

        $cents = (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');

        return $cents > 0 ? $cents : null;
    }
}
