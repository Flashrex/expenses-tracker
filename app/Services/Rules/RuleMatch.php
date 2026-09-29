<?php

namespace App\Services\Rules;

use App\Models\Rule;

final readonly class RuleMatch
{
    public function __construct(
        public ?string $groupKey,
        public int $shareDivisor,
        public bool $ignored,
        public ?string $ruleId,
    ) {}

    /**
     * No rule matched.
     */
    public static function none(): self
    {
        return new self(null, 1, false, null);
    }

    public static function fromRule(Rule $rule): self
    {
        if ($rule->ignore) {
            return new self(null, 1, true, $rule->id);
        }

        return new self($rule->group_key, $rule->share_divisor, false, $rule->id);
    }

    /**
     * @return array{group_key: ?string, share_divisor: int, ignored: bool, rule_id: ?string}
     */
    public function toArray(): array
    {
        return [
            'group_key' => $this->groupKey,
            'share_divisor' => $this->shareDivisor,
            'ignored' => $this->ignored,
            'rule_id' => $this->ruleId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            groupKey: $data['group_key'],
            shareDivisor: $data['share_divisor'],
            ignored: $data['ignored'],
            ruleId: $data['rule_id'],
        );
    }

    /**
     * How the entry ends up: "ignored", "group", "unassigned" (outgoing, no group) or "income".
     */
    public function state(string $direction): string
    {
        return match (true) {
            $this->ignored => 'ignored',
            $this->groupKey !== null => 'group',
            $direction === 'out' => 'unassigned',
            default => 'income',
        };
    }
}
