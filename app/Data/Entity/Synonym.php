<?php
/**

 */

namespace SzentirasHu\Data\Entity;

use Eloquent;

/**
 * 
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property string $word
 * @property int $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Synonym newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Synonym newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Synonym query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Synonym whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Synonym whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Synonym whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Synonym whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Synonym whereWord($value)
 * @mixin Eloquent
 */
class Synonym extends Eloquent {

} 