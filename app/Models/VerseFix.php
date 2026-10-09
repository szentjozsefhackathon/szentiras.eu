<?php

namespace SzentirasHu\Models;

use Database\Factories\VerseFixFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Enum\VerseFixStatus;

/**
 * A manual correction of one stored verse record (translation + gepi + tip),
 * replacing original_text with fixed_text. Re-applied after every import.
 *
 * @property int $id
 * @property int $translation_id
 * @property string $usx_code
 * @property string $gepi
 * @property int $chapter
 * @property int $numv
 * @property int $tip
 * @property string $original_text
 * @property string $fixed_text
 * @property string|null $note
 * @property VerseFixStatus $status
 * @property \Illuminate\Support\Carbon|null $last_applied_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Translation|null $translation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VerseFixRevision> $revisions
 */
class VerseFix extends Model
{
    /** @use HasFactory<VerseFixFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'translation_id',
        'usx_code',
        'gepi',
        'chapter',
        'numv',
        'tip',
        'original_text',
        'fixed_text',
        'note',
        'status',
        'last_applied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'translation_id' => 'integer',
            'chapter' => 'integer',
            'numv' => 'integer',
            'tip' => 'integer',
            'status' => VerseFixStatus::class,
            'last_applied_at' => 'datetime',
        ];
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(VerseFixRevision::class)->orderByDesc('id');
    }
}
