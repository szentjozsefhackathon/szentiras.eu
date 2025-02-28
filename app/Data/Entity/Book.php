<?php

namespace SzentirasHu\Data\Entity;
use Eloquent;

/**
 * Description of Book
 *
 * @property string abbrev
 * @property int id
 * @property  int number
 * @author berti
 */
class Book extends Eloquent {
    protected $fillable = [
        'name',
        'abbrev',
        'link',
        'old_testament',
        'order',
        'usx_code',
    ];

    public function verses() {
        return $this->hasMany('SzentirasHu\Data\Entity\Verse', 'book_id');
    }

    public function translation() {
        return $this->belongsTo('SzentirasHu\Data\Entity\Translation');
    }

}
