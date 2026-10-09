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
