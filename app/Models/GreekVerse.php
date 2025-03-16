<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $source
 * @property string $usx_code
 * @property int $chapter
 * @property int $verse
 * @property string $text
 * @property string $json
 * @property string $strongs
 * @property string $strong_transliterations
 * @property string $strong_normalizations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Models\StrongWord> $strongWords
 * @property-read int|null $strong_words_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereStrongNormalizations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereStrongTransliterations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereStrongs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereUsxCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereVerse($value)
 * @property string $gepi
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereGepi($value)
 * @mixin \Eloquent
 */
class GreekVerse extends Model
{
    public function strongWords() {
        return $this->belongsToMany(StrongWord::class)->withPivot('strong_word_instances');
    }
}
