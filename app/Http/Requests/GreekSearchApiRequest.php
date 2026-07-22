<?php

namespace SzentirasHu\Http\Requests;

use SzentirasHu\Service\Search\GreekSearchMode;
use SzentirasHu\Service\Search\GreekSearchRule;

/**
 * The filters of the Greek search API: the same ones the full text search takes, plus the
 * choice between searching among the Strong words and searching in the Greek text.
 */
class GreekSearchApiRequest extends SearchApiRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'mode' => ['nullable', 'in:strong,lemma,verse'],
            'rule' => ['nullable', 'in:all,any'],
        ]);
    }

    public function mode(): GreekSearchMode
    {
        return GreekSearchMode::tryFrom((string) $this->query('mode')) ?? GreekSearchMode::Lemma;
    }

    public function rule(): GreekSearchRule
    {
        return GreekSearchRule::tryFrom((string) $this->query('rule')) ?? GreekSearchRule::All;
    }
}
