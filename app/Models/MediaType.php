<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SzentirasHu\Models\MediaType
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $name
 * @property string|null $website
 * @property string|null $license
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Models\Media> $media
 * @property-read int|null $media_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType whereLicense($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaType whereWebsite($value)
 * @mixin \Eloquent
 */
class MediaType extends Model
{
    protected $fillable = ['name', 'website', 'license'];

    public function media()
    {
        return $this->hasMany(Media::class);
    }
}
