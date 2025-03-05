<?php

namespace SzentirasHu\Data\Entity;
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

    public function getType() {
        if (!self::$typeMap) {
            foreach (\Config::get('translations') as $translationId => $typeDefs) {
                $id = $typeDefs['id'];
                foreach($typeDefs['verseTypes'] as $typeName => $typeIds) {
                    foreach ($typeIds as $key => $typeId) {
                        if ($typeName == 'heading') {
                            $t = $typeName.$key;
                        } else {
                            $t = $typeName;
                        }
                        self::$typeMap[$id][$typeId] = $t;
                    }
                }
            }
        }
        if (array_key_exists($this->tip, self::$typeMap[$this->trans])) {
            return self::$typeMap[$this->trans][$this->tip];
        } else {
            return 'unknown';
        }

    }

}
