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
 * @property string $etymology
 * @property string $source
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology whereEtymology($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology whereStrongWordNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Etymology whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Etymology extends Model
{
    //
}
