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
