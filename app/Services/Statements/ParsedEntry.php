<?php

namespace App\Services\Statements;

final readonly class ParsedEntry
{
    public function __construct(
        public string $bookedOn,
        public string $valueOn,
        public string $type,
        public ?string $counterparty,
        public string $purpose,
        public string $merchant,
        public int $amountCents,
    ) {}

    /**
     * "out" for money leaving the account, "in" otherwise.
     */
    public function direction(): string
    {
        return $this->amountCents < 0 ? 'out' : 'in';
    }

    /**
     * @return array{booked_on: string, value_on: string, type: string, counterparty: ?string, purpose: string, merchant: string, amount_cents: int, direction: string}
     */
    public function toArray(): array
    {
        return [
            'booked_on' => $this->bookedOn,
            'value_on' => $this->valueOn,
            'type' => $this->type,
            'counterparty' => $this->counterparty,
            'purpose' => $this->purpose,
            'merchant' => $this->merchant,
            'amount_cents' => $this->amountCents,
            'direction' => $this->direction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            bookedOn: $data['booked_on'],
            valueOn: $data['value_on'],
            type: $data['type'],
            counterparty: $data['counterparty'],
            purpose: $data['purpose'],
            merchant: $data['merchant'],
            amountCents: $data['amount_cents'],
        );
    }
}
