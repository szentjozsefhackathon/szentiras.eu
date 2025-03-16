<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;

/**
 * 
 *
 * @property int $id
 * @property string $source
 * @property string $gepi
 * @property string $usx_code
 * @property int $chapter
 * @property int $verse
 * @property string $model
 * @property \Pgvector\Laravel\Vector $embedding
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding whereChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding whereGepi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding whereUsxCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseEmbedding whereVerse($value)
 * @mixin \Eloquent
 */
class GreekVerseEmbedding extends Model
{
    public $timestamps = false;

    protected $casts = [
        'embedding' => Vector::class,
    ];
}
