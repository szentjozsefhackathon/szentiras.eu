<?php

namespace SzentirasHu\Models;

use Database\Factories\VerseFixRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SzentirasHu\Data\Enum\VerseFixStatus;

/**
 * A snapshot of a verse fix after each change, made either by an editor or by an import.
 *
 * @property int $id
 * @property int $verse_fix_id
 * @property string $original_text
 * @property string $fixed_text
 * @property string|null $note
 * @property VerseFixStatus $status
 * @property string $source
 * @property string|null $editor_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read VerseFix $verseFix
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\VerseFixRevisionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereEditorToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereFixedText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereOriginalText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseFixRevision whereVerseFixId($value)
 * @mixin \Eloquent
 */
class VerseFixRevision extends Model
{
    /** @use HasFactory<VerseFixRevisionFactory> */
    use HasFactory;

    public const SOURCE_EDITOR = 'editor';

    public const SOURCE_IMPORT = 'import';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'verse_fix_id',
        'original_text',
        'fixed_text',
        'note',
        'status',
        'source',
        'editor_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VerseFixStatus::class,
        ];
    }

    public function verseFix(): BelongsTo
    {
        return $this->belongsTo(VerseFix::class);
    }
}
