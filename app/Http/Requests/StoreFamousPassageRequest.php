<?php

namespace SzentirasHu\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SzentirasHu\Service\FamousPassage\FamousPassageService;

class StoreFamousPassageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', $this->uniqueSlugRule()],
            'alternative_names' => ['nullable', 'string', 'max:1000'],
            'reference' => ['required', 'string', 'max:100', $this->referenceRule()],
            'key_verse' => ['nullable', 'string', 'max:100', $this->referenceRule()],
            'introduction' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A szakasz nevét meg kell adni.',
            'name.max' => 'A név legfeljebb 255 karakter hosszú lehet.',
            'slug.regex' => 'Az URL-részlet csak kisbetűt, számot és kötőjelet tartalmazhat (pl. 23-zsoltar).',
            'slug.unique' => 'Ez az URL-részlet már foglalt.',
            'reference.required' => 'A szentírási helyet meg kell adni (pl. Zsolt 23 vagy Mt 5,1-12).',
            'seo_description.max' => 'A leírás legfeljebb 320 karakter hosszú lehet.',
            'introduction.max' => 'A bevezető legfeljebb 1000 karakter hosszú lehet.',
        ];
    }

    /**
     * The alternative names, one per line.
     *
     * @return list<string>
     */
    public function alternativeNames(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\R/u', (string) $this->validated('alternative_names', '')) ?: [],
        )));
    }

    protected function uniqueSlugRule(): \Illuminate\Validation\Rules\Unique
    {
        return Rule::unique('famous_passages', 'slug');
    }

    private function referenceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (app(FamousPassageService::class)->locate((string) $value) === null) {
                $fail("Ez a hely nem található az alapértelmezett fordításban: {$value}. Egy könyvön belüli hivatkozást adj meg, pl. Zsolt 23 vagy Mt 5,1-12.");
            }
        };
    }
}
