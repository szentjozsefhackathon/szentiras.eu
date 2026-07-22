<?php

namespace SzentirasHu\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SzentirasHu\Service\Search\BookFilter;
use SzentirasHu\Service\Search\UnknownBookException;

/**
 * The filters of the full text search API, mirroring the options of the search form.
 */
class SearchApiRequest extends FormRequest
{
    /**
     * The largest number of verses a single search may return, so that a caller cannot ask
     * for the whole Bible in one response.
     */
    public const MAX_LIMIT = 1000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'book' => ['nullable', 'string', $this->bookRule()],
            'grouping' => ['nullable', 'in:verse,chapter,book'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }

    /**
     * The books to search in, keyed by USX code. Empty means the whole Bible.
     *
     * @return array<string, mixed>
     */
    public function usxCodes(): array
    {
        return BookFilter::usxCodesFor($this->query('book'));
    }

    public function limit(): ?int
    {
        $limit = $this->query('limit');

        return $limit === null ? null : (int) $limit;
    }

    public function grouping(): ?string
    {
        return $this->query('grouping');
    }

    /**
     * Accepts everything the form's book selector offers, plus Hungarian book abbreviations.
     */
    protected function bookRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            try {
                BookFilter::usxCodesFor($value);
            } catch (UnknownBookException $exception) {
                $fail($exception->getMessage());
            }
        };
    }
}
