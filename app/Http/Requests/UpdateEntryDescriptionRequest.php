<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEntryDescriptionRequest extends FormRequest
{
    public const MAX_LENGTH = 500;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The global middleware has already trimmed the text and turned an empty one into null.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['present', 'nullable', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }
}
