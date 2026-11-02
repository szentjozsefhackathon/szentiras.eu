<?php

namespace SzentirasHu\Http\Requests;

use Illuminate\Support\Arr;

/**
 * The filters of the extended search API: the book and limit of the full text search, plus
 * the quorum and whether to collapse the translations of a verse into one hit. It does not
 * group by chapter or book, its hits are always verses.
 */
class ExtendedSearchApiRequest extends SearchApiRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(Arr::except(parent::rules(), 'grouping'), [
            'quorum' => ['nullable', 'numeric', 'gt:0', 'max:1'],
            'onePerVerse' => ['nullable', 'in:0,1,true,false'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quorum.gt' => 'A quorum a szavak aránya, 0-nál nagyobb és legfeljebb 1.',
            'quorum.max' => 'A quorum a szavak aránya, 0-nál nagyobb és legfeljebb 1.',
        ];
    }

    public function quorum(): ?float
    {
        $quorum = $this->query('quorum');

        return $quorum === null ? null : (float) $quorum;
    }

    public function onePerVerse(): bool
    {
        return $this->boolean('onePerVerse');
    }
}
