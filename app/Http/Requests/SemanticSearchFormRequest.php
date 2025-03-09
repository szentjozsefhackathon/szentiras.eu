<?php

namespace SzentirasHu\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SemanticSearchFormRequest extends FormRequest
{

    protected int $maxLength = 2000;

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
            'textToSearchAi' => "required|max:{$this->maxLength}",
        ];
    }

    public function messages(): array
{
    return [
        'textToSearchAi.max' => "A keresett szöveg maximum {$this->maxLength} karakter hosszú lehet.",
        'textToSearchAi.required' => "A keresett szöveget meg kell adni.",

    ];

}

}
