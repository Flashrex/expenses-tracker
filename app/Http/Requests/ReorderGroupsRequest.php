<?php

namespace App\Http\Requests;

use App\Models\Group;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderGroupsRequest extends FormRequest
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
        return [
            'groups' => ['required', 'array', $this->otherLast(...)],
            'groups.*' => ['string', 'distinct'],
        ];
    }

    /**
     * "Other" always stays at the end.
     */
    private function otherLast(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) && end($value) !== Group::OTHER) {
            $fail('"Other" must stay last.');
        }
    }
}
