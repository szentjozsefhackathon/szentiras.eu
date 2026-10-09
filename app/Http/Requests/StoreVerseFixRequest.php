<?php

namespace SzentirasHu\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVerseFixRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Textareas submit CRLF line endings, verse texts are stored with LF.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('fixed_text'))) {
            $this->merge(['fixed_text' => str_replace("\r\n", "\n", $this->input('fixed_text'))]);
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
            'verse_id' => ['required', 'integer'],
            'fixed_text' => ['required', 'string', 'max:20000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verse_id.required' => 'Hiányzik a javítandó vers azonosítója.',
            'fixed_text.required' => 'A javított szöveget meg kell adni.',
            'fixed_text.max' => 'A javított szöveg legfeljebb 20000 karakter hosszú lehet.',
            'note.max' => 'A megjegyzés legfeljebb 2000 karakter hosszú lehet.',
        ];
    }
}
