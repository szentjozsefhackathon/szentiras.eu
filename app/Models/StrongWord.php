<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property int $id
 * @property int $number
 * @property string $lemma
 * @property string $transliteration
 * @property string $normalized
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Models\GreekVerse> $greekVerses
 * @property-read int|null $greek_verses_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord whereLemma($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord whereNormalized($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord whereTransliteration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrongWord whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class StrongWord extends Model
{
    
    public function greekVerses() : BelongsToMany {
        return $this->belongsToMany(GreekVerse::class);
    }

    public function dictionaryMeanings() : HasMany {
        return $this->hasMany(DictionaryMeaning::class, 'strong_word_number', 'number');
    }

}
