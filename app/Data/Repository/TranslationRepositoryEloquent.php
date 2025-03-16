<?php
/**

 */

namespace SzentirasHu\Data\Repository;

use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Translation;

class TranslationRepositoryEloquent implements TranslationRepository
{

    public function getAll() : Collection
    {
        $allTranslations = \Cache::remember(
           'getAllTranslations', 120, function () {
            return Translation::orderBy('order')->orderBy('name')->whereIn('id', \Config::get('settings.enabledTranslations'))->get();
        });
        return $allTranslations;
    }

    public function getByDenom($denom = false) : Collection
    {
        $q = $denom ? Translation::where('denom', $denom) : Translation::all();
        return $q->orderBy('denom')->orderBy('order')->orderBy('name')->whereIn('id', \Config::get('settings.enabledTranslations'))->get();
    }


    public function getAllOrderedByDenom() : Collection
    {
        $allTranslations = \Cache::remember(
            'translations_allOrderedByDenom', 120, function () {
            return Translation::orderBy('denom')->orderBy('order')->whereIn('id', \Config::get('settings.enabledTranslations'))->get();
        });
        return $allTranslations;

    }

    public function getBooks($translation)
    {
        return $translation->books()->orderBy('order')->get();
    }

    public function getByAbbrev($abbrev)
    {
        return \Cache::remember(
            "translations_getByAbbrev_{$abbrev}", 120, function() use ($abbrev) {
            return Translation::byAbbrev($abbrev);
            });
    }

    public function getById($id)
    {
        return \Cache::remember(
            "translations_getById_{$id}", 120, function() use ($id) {
            return Translation::find($id);
        });
    }

}