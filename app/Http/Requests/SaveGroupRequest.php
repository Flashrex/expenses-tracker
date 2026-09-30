<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRuleCards;
use App\Models\Group;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveGroupRequest extends FormRequest
{
    use ValidatesRuleCards;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Colours are compared and stored lowercase.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('color'))) {
            $this->merge(['color' => strtolower($this->input('color'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40', $this->uniqueName(...)],
            'color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'before' => ['nullable', 'string'],
            ...$this->ruleRules(withShare: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name.',
            'name.max' => 'Use at most 40 characters.',
            'color.*' => 'Pick a colour.',
            ...$this->ruleMessages(),
        ];
    }

    /**
     * No other group may have the same name, ignoring case.
     */
    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $current = $this->route('group');
        $name = mb_strtolower(trim($value));

        $taken = Group::query()->get(['key', 'name'])->contains(
            fn (Group $group) => $group->key !== $current?->key && mb_strtolower(trim($group->name)) === $name
        );

        if ($taken) {
            $fail('Another group already has this name.');
        }
    }
}
