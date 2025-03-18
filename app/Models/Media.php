<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $uuid
 * @property string $filename
 * @property string $mime_type
 * @property int $media_type_id
 * @property string|null $usx_code
 * @property int|null $chapter
 * @property int|null $verse
 * @property-read \SzentirasHu\Models\MediaType $mediaType
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereMediaTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereUsxCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereVerse($value)
 * @mixin \Eloquent
 */
class Media extends Model
{
    protected $fillable = ['filename', 'mime_type', 'media_type_id', 'usx_code', 'chapter', 'verse', 'uuid'];

    public function mediaType() : BelongsTo
    {
        return $this->belongsTo('SzentirasHu\Models\MediaType');
    }
}
