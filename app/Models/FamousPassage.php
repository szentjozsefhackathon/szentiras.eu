<?php

namespace SzentirasHu\Models;

use Database\Factories\FamousPassageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A well-known Bible passage people search for by name ("23. zsoltár", "Hegyi beszéd"), with
 * its own page that gathers the text in every translation, its context and explanation.
 *
 * The reference is written in the default translation's notation ("Zsolt 23", "Mt 5,1-12");
 * usx_code and the chapters are derived from it on save, so chapter pages can find their passages.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property list<string> $alternative_names
 * @property string $reference
 * @property string|null $key_verse
 * @property string $usx_code
 * @property int $chapter_from
 * @property int $chapter_to
 * @property string|null $introduction
 * @property string|null $content
 * @property list<array{key: string, title: string, total: int, citations: list<array<string, string|null>>}>|null $church_citations
 * @property \Illuminate\Support\Carbon|null $church_citations_fetched_at
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property bool $is_active
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FamousPassage extends Model
{
    /** @use HasFactory<FamousPassageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'alternative_names',
        'reference',
        'key_verse',
        'usx_code',
        'chapter_from',
        'chapter_to',
        'introduction',
        'content',
        'church_citations',
        'church_citations_fetched_at',
        'seo_title',
        'seo_description',
        'is_active',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alternative_names' => 'array',
            'church_citations' => 'array',
            'church_citations_fetched_at' => 'datetime',
            'chapter_from' => 'integer',
            'chapter_to' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param Builder<FamousPassage> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    /**
     * Passages that cover at least part of the given chapter.
     *
     * @param Builder<FamousPassage> $query
     */
    public function scopeInChapter(Builder $query, string $usxCode, int $chapter): void
    {
        $query->where('usx_code', $usxCode)
            ->where('chapter_from', '<=', $chapter)
            ->where('chapter_to', '>=', $chapter);
    }

    /**
     * "23. zsoltár – Az Úr az én pásztorom": the name with its best-known alternative.
     */
    public function displayName(): string
    {
        $alternativeName = $this->alternative_names[0] ?? null;

        return $alternativeName === null ? $this->name : "{$this->name} – {$alternativeName}";
    }
}
