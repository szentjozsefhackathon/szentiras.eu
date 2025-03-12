<?php

namespace SzentirasHu\Data\Entity;
use Eloquent;
use Illuminate\Support\Facades\Config;

/**
 * Domain object representing a translation.
 *
 * @property mixed id
 * @property string name
 * @property string abbrev
 * @author berti
 */
class Translation extends Eloquent {

    public static function byAbbrev($translationAbbrev)
    {
        return self::where('abbrev', $translationAbbrev)->first();
    }

    public function books() {
        return $this->hasMany('SzentirasHu\Data\Entity\Book');
    }

    public static function getAbbrevById($translationId) {
        $translationSettings = Config::get('translations.ids');
        return $translationSettings[$translationId];
    }

    public static function getOrderById($translationId) {
        $translationSettings = Config::get('translations.ids');
        return Config::get("translations.definitions." . $translationSettings[$translationId] . ".order");
    }
}