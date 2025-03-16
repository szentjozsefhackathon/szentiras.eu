<?php

namespace SzentirasHu\Data\Entity;
use Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Description of Book
 *
 * @author berti
 * @property int $order
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property int $translation_id
 * @property string $name
 * @property string $abbrev
 * @property string $link
 * @property int $old_testament
 * @property int $id
 * @property string|null $usx_code
 * @property-read \SzentirasHu\Data\Entity\Translation|null $translation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Data\Entity\Verse> $verses
 * @property-read int|null $verses_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereAbbrev($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereOldTestament($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereTranslationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Book whereUsxCode($value)
 * @mixin Eloquent
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
        return $this->hasMany(Verse::class);
    }

    
    public function translation() : BelongsTo {
        return $this->belongsTo(Translation::class);
    }

}
