<?php

namespace SzentirasHu\Data\Entity;

use Config;
use Eloquent;

/**
 * This class represents ONE database record for a given bible verse, that means, its type will vary.
 *
 * @property string verse
 * @property int tip
 * @property int chapter
 * @property int numv
 * @author berti
 */
class Verse extends Eloquent {

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

    public function book() {
        return $this->belongsTo('SzentirasHu\Data\Entity\Book');
    }

    public function books() {
        return $this->belongsTo('SzentirasHu\Data\Entity\Book', 'usx_code');
    }

    public function translation() {
        return $this->belongsTo('SzentirasHu\Data\Entity\Translation','trans');
    }

    public static function getTypeMap() {
        if (!self::$typeMap) {
            foreach (Config::get('translations') as $translationAbbrev => $typeDefs) {
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
        }
        return self::$typeMap;
    }

    public static function getHeadingTypes($translationId) {
        $typeMap = self::getTypeMap();
        $headingTypes = [];
        foreach ($typeMap as $types) {
            foreach ($types as $typeId => $typeName) {
                if (strpos($typeName, 'heading') !== false) {
                    $headingTypes[$translationId][] = $typeId;
                }
            }
        }
        return $headingTypes[$translationId];
    }

    public function getType() {
        $typeMap = self::getTypeMap();
        if (array_key_exists($this->tip, $typeMap[$this->trans] ?? [] )) {
            return $typeMap[$this->trans][$this->tip];
        } else {
            return 'unknown';
        }

    }

}
