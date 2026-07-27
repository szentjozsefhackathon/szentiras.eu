<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $source
 * @property string $usx_code
 * @property int $chapter
 * @property int $verse
 * @property string $text
 * @property string $json
 * @property string $strongs
 * @property string $strong_transliterations
 * @property string $strong_normalizations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Models\StrongWord> $strongWords
 * @property-read int|null $strong_words_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereStrongNormalizations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereStrongTransliterations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereStrongs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereUsxCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereVerse($value)
 * @property string $gepi
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereGepi($value)
 * @property string|null $transliteration
 * @property string|null $normalization
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereNormalization($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerse whereTransliteration($value)
 * @property-read array<int, array{printed: string, strong: ?string, translit: ?string, usx_code: string, chapter: int, verse: int, i: int, hasBreak: bool}> $annotatedWords
 * @property int|null $verse_analysis_id
 * @property-read bool $has_verse_analysis
 * @mixin \Eloquent
 */
class GreekVerse extends Model
{
    /**
     * Add only the matching analysis ID, keeping the larger JSONB payload out of chapter queries.
     *
     * @param  Builder<GreekVerse>  $query
     * @return Builder<GreekVerse>
     */
    public function scopeWithVerseAnalysisAvailability(
        Builder $query,
        string $locale = GreekVerseAnalysis::DEFAULT_LOCALE,
    ): Builder {
        return $query->addSelect([
            'verse_analysis_id' => GreekVerseAnalysis::query()
                ->select('id')
                ->whereColumn('greek_verse_analyses.gepi', 'greek_verses.gepi')
                ->whereColumn('greek_verse_analyses.greek_source', 'greek_verses.source')
                ->where('greek_verse_analyses.locale', $locale)
                ->limit(1),
        ]);
    }

    public function getHasVerseAnalysisAttribute(): bool
    {
        return $this->getAttribute('verse_analysis_id') !== null;
    }

    /**
     * @return BelongsToMany<StrongWord, $this>
     */
    public function strongWords(): BelongsToMany {
        return $this->belongsToMany(StrongWord::class)->withPivot('strong_word_instances');
    }

    /**
     * Annotate each Greek word of the verse with the data needed to look up its
     * meaning through the {@see \SzentirasHu\Http\Controllers\Ai\AiController::getGreekWordPanel()} endpoint.
     *
     * The word index `i` matches the ordering of the verse `json` array, i.e. the
     * tokens of the verse text with the paragraph marker removed and split on spaces.
     *
     * @return array<int, array{printed: string, strong: ?string, translit: ?string, usx_code: string, chapter: int, verse: int, i: int, hasBreak: bool}>
     */
    /**
     * Accessor so templates can read `greekVerse.annotatedWords` without Eloquent
     * mistaking it for a relationship.
     *
     * @return array<int, array{printed: string, strong: ?string, translit: ?string, usx_code: string, chapter: int, verse: int, i: int, hasBreak: bool}>
     */
    public function getAnnotatedWordsAttribute(): array
    {
        return $this->annotatedWords();
    }

    public function annotatedWords(): array
    {
        $cleanedTokens = explode(' ', str_replace('¶', '', $this->text));
        $originalTokens = explode(' ', $this->text);
        $strongs = explode(' ', (string) $this->strongs);
        $transliterations = explode(' ', (string) $this->strong_transliterations);

        $words = [];
        foreach ($cleanedTokens as $i => $token) {
            $words[] = [
                'printed' => $token,
                'strong' => $strongs[$i] ?? null,
                'translit' => $transliterations[$i] ?? null,
                'usx_code' => $this->usx_code,
                'chapter' => $this->chapter,
                'verse' => $this->verse,
                'i' => $i,
                'hasBreak' => isset($originalTokens[$i]) && str_contains($originalTokens[$i], '¶'),
            ];
        }

        return $words;
    }
}
