<?php

namespace App\Http\Requests;

use App\Services\Groups\GroupCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteGroupRequest extends FormRequest
{
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
        $others = array_values(array_diff(app(GroupCatalog::class)->keys(), [$this->route('group')->key]));

        return [
            'move_to' => ['required', 'string', Rule::in($others)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'move_to.*' => 'Pick the group its entries move to.',
        ];
    }
}
