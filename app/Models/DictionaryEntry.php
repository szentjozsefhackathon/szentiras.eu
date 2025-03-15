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
 * @property string $paradigm
 * @property string $meaning
 * @property string $explanation
 * @property int $order
 * @property string $source
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereExplanation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereMeaning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereParadigm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereStrongWordNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DictionaryEntry whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DictionaryEntry extends Model
{
    //
}
