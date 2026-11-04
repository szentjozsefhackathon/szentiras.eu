<?php

namespace SzentirasHu\Models;

use Database\Factories\GuideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property bool $is_active
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Database\Factories\GuideFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Guide whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Guide extends Model
{
    /** @use HasFactory<GuideFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'is_active',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
