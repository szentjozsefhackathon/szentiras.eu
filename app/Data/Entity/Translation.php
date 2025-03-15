<?php

namespace SzentirasHu\Data\Entity;
use Eloquent;
use Illuminate\Support\Facades\Config;

/**
 * Domain object representing a translation.
 *
 * @author berti
 * @property int $id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property string $name
 * @property string $abbrev
 * @property int $order
 * @property string $denom
 * @property string $lang
 * @property string $copyright
 * @property string $publisher
 * @property string $publisher_url
 * @property string $reference
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Data\Entity\Book> $books
 * @property-read int|null $books_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereAbbrev($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereCopyright($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereDenom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation wherePublisher($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation wherePublisherUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereUpdatedAt($value)
 * @mixin Eloquent
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