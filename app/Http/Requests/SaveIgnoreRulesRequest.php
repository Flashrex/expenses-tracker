<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRuleCards;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveIgnoreRulesRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->ruleRules(withShare: false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->ruleMessages();
    }
}
