<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $strong_word_number
 * @property string $meaning
 * @property string $explanation
 * @property string $source
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning whereExplanation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning whereMeaning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning whereStrongWordNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning whereUpdatedAt($value)
 * @property int $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryMeaning whereOrder($value)
 * @mixin \Eloquent
 */
class DictionaryMeaning extends Model
{
    //
}
