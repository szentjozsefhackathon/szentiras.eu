<?php

namespace SzentirasHu\Data\Entity;
use Eloquent;

/**
 * Model for possible book abbreviations. They can represent bad abbreviations as well.
 *
 * @author berti
 * @property int $id
 * @property string|null $abbrev
 * @property int $books_id
 * @property int|null $translation_id
 * @property-read \SzentirasHu\Data\Entity\Book|null $books
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookAbbrev newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookAbbrev newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookAbbrev query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookAbbrev whereAbbrev($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookAbbrev whereBooksId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookAbbrev whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookAbbrev whereTranslationId($value)
 * @mixin Eloquent
 */
class BookAbbrev extends Eloquent {

    public $timestamps=false;

    public function books() {
        return $this->belongsTo('SzentirasHu\\Data\\Entity\\Book', 'books_id', 'number');
    }
    
}
