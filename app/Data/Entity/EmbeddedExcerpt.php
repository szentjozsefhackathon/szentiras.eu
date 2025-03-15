<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

/**
 * 
 *
 * @property int $id
 * @property string $hash
 * @property string $model
 * @property string $reference
 * @property int|null $chapter
 * @property int|null $verse
 * @property int|null $to_chapter
 * @property int|null $to_verse
 * @property string|null $gepi
 * @property string $translation_abbrev
 * @property string $usx_code
 * @property \SzentirasHu\Data\Entity\EmbeddedExcerptScope $scope
 * @property \Pgvector\Laravel\Vector $embedding
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt nearestNeighbors(string $column, ?mixed $value, \Pgvector\Laravel\Distance $distance)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereGepi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereToChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereToVerse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereTranslationAbbrev($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereUsxCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmbeddedExcerpt whereVerse($value)
 * @mixin \Eloquent
 */
class EmbeddedExcerpt extends Model
{

    use HasNeighbors;

    protected $casts = [
        'embedding' => Vector::class,
        'scope' => EmbeddedExcerptScope::class
    ];

    public $timestamps = false;

}
