<?php

namespace SzentirasHu\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderGuidesRequest extends FormRequest
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
            'guides' => ['required', 'array'],
            'guides.*' => ['required', 'integer', 'distinct', 'exists:guides,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guides.required' => 'A mentendő sorrend hiányzik.',
            'guides.*.distinct' => 'Egy bejegyzés csak egyszer szerepelhet a sorrendben.',
            'guides.*.exists' => 'Az egyik bejegyzés már nem létezik.',
        ];
    }
}
