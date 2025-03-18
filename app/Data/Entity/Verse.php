<?php

namespace SzentirasHu\Data\Entity;

use Cache;
use Config;
use Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * This class represents ONE database record for a given bible verse, that means, its type will vary.
 *
 * @author berti
 * @property int $trans
 * @property string $gepi
 * @property int $chapter
 * @property int $numv
 * @property int $tip
 * @property string|null $verse
 * @property string|null $verseroot
 * @property string|null $ido
 * @property int $id
 * @property int $book_id
 * @property string $usx_code
 * @property-read \SzentirasHu\Data\Entity\Book $book
 * @property-read \SzentirasHu\Data\Entity\Book|null $books
 * @property-read \SzentirasHu\Data\Entity\Translation|null $translation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereBookId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereGepi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereIdo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereNumv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereTip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereTrans($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereUsxCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereVerse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verse whereVerseroot($value)
 * @mixin Eloquent
 */
class Verse extends Eloquent
{

    public $timestamps = false;
    protected $table = 'tdverse';

    protected $fillable = [
        'usx_code',
        'gepi',
        'verse',
        'order',
        'chapter',
        'numv',
        'tip',
        'verseroot',
        'ido',
    ];

    private static $typeMap;

    public function book() : BelongsTo
    {
        return $this->belongsTo('SzentirasHu\Data\Entity\Book');
    }

    public function translation() : BelongsTo
    {
        return $this->belongsTo(\SzentirasHu\Data\Entity\Translation::class, 'trans');
    }

    public static function getTypeMap()
    {
        return Cache::remember('typeMap', 60, function () {
            foreach (Config::get('translations.definitions') as $translationAbbrev => $typeDefs) {
                $translationId = $typeDefs['id'];
                foreach($typeDefs['verseTypes'] as $typeName => $typeIds) {
                    foreach ($typeIds as $typeId => $typeValue) {
                        if ($typeName == 'heading') {
                            $t = $typeName . $typeIds[$typeId];
                            self::$typeMap[$translationId][$typeId] = $t;
                        } else {
                            $t = $typeName;
                            self::$typeMap[$translationId][$typeValue] = $t;
                        }
                    }
                }
            }
            return self::$typeMap;
        });
    }

    public static function getHeadingTypes($translationAbbrev)
    {
        $typeMap = self::getTypeMap();
        $headingTypes = [];
        foreach ($typeMap as $types) {
            foreach ($types as $typeId => $typeName) {
                if (strpos($typeName, 'heading') !== false) {
                    $headingTypes[$translationAbbrev][] = $typeId;
                }
            }
        }
        return $headingTypes[$translationAbbrev];
    }

    public function getType()
    {
        $typeMap = self::getTypeMap();
        if (array_key_exists($this->tip, $typeMap[$this->trans] ?? [])) {
            return $typeMap[$this->trans][$this->tip];
        } else {
            return 'unknown';
        }
    }
}
