<?php

namespace SzentirasHu\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSelfServiceApiKeyRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A kulcs nevét meg kell adni.',
            'name.max' => 'A kulcs neve legfeljebb 255 karakter hosszú lehet.',
            'description.max' => 'A leírás legfeljebb 1000 karakter hosszú lehet.',
        ];
    }
}
