<?php

namespace SzentirasHu\Models;

use Database\Factories\BookIntroductionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SzentirasHu\Data\Entity\Translation;

/**
 * An introduction printed in a Bible edition, either before a single book or
 * before a group of books (e.g. "Bevezetés a Törvény öt könyvéhez").
 *
 * Introductions are keyed by USX code rather than book id, because a text
 * import deletes and recreates the book rows of a translation.
 *
 * @property int $id
 * @property int $translation_id
 * @property list<string> $usx_codes
 * @property bool $is_group
 * @property string|null $title
 * @property list<array{style: string, segments: list<array{text: string, italic?: bool, reference?: string}>}> $paragraphs
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Translation|null $translation
 */
class BookIntroduction extends Model
{
    /** @use HasFactory<BookIntroductionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'translation_id',
        'usx_codes',
        'is_group',
        'title',
        'paragraphs',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usx_codes' => 'array',
            'is_group' => 'boolean',
            'paragraphs' => 'array',
            'position' => 'integer',
        ];
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }

    /**
     * Introductions of the given book in the given translation: the group
     * introductions first, then the book's own, each in print order.
     *
     * @param  Builder<BookIntroduction>  $query
     */
    public function scopeForBook(Builder $query, int $translationId, string $usxCode): void
    {
        $query->where('translation_id', $translationId)
            ->whereJsonContains('usx_codes', $usxCode)
            ->orderByDesc('is_group')
            ->orderBy('position');
    }
}
