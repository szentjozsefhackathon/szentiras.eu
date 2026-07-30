<?php

namespace SzentirasHu\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreGuideRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'tags' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $tagNames = collect(explode(',', (string) $value))
                        ->map(fn (string $tagName): string => trim($tagName))
                        ->filter();

                    if ($tagNames->count() > 10) {
                        $fail('Legfeljebb 10 címke adható meg.');
                    }

                    if ($tagNames->contains(fn (string $tagName): bool => mb_strlen($tagName) > 50)) {
                        $fail('Egy címke legfeljebb 50 karakter hosszú lehet.');
                    }

                    if ($tagNames->contains(fn (string $tagName): bool => Str::slug($tagName) === '')) {
                        $fail('Minden címkének tartalmaznia kell betűt vagy számot.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A bejegyzés címét meg kell adni.',
            'title.max' => 'A cím legfeljebb 255 karakter hosszú lehet.',
            'content.required' => 'A bejegyzés tartalmát meg kell adni.',
            'tags.max' => 'A címkék együtt legfeljebb 500 karakter hosszúak lehetnek.',
        ];
    }
}
