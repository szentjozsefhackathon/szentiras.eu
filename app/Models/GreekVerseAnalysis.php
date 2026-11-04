<?php

namespace SzentirasHu\Models;

use Database\Factories\GreekVerseAnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $gepi
 * @property string $greek_source
 * @property string $locale
 * @property int $format_version
 * @property array<string, mixed> $analysis
 * @property string $generated_by
 * @property Carbon $generated_at
 * @property string $source_key
 * @property string $content_hash
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @method static \Database\Factories\GreekVerseAnalysisFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereAnalysis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereContentHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereFormatVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereGeneratedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereGepi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereGreekSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereSourceKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GreekVerseAnalysis whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GreekVerseAnalysis extends Model
{
    /** @use HasFactory<GreekVerseAnalysisFactory> */
    use HasFactory;

    public const DEFAULT_LOCALE = 'hu';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gepi',
        'greek_source',
        'locale',
        'format_version',
        'analysis',
        'generated_by',
        'generated_at',
        'source_key',
        'content_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format_version' => 'integer',
            'analysis' => 'array',
            'generated_at' => 'date',
        ];
    }
}
