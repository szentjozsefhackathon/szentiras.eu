<?php

namespace SzentirasHu\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use SzentirasHu\Models\FamousPassage;

class UpdateFamousPassageRequest extends StoreFamousPassageRequest
{
    protected function uniqueSlugRule(): Unique
    {
        /** @var FamousPassage $famousPassage */
        $famousPassage = $this->route('famousPassage');

        return Rule::unique('famous_passages', 'slug')->ignore($famousPassage->id);
    }
}
