<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\GreekVerseAnalysis;
use SzentirasHu\Test\Common\TestCase;

class VerseAnalysisImportTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT_PATH = 'greek/verse-analysis/OpenGNT/hu/v1';

    private const DISK = 'verse-analysis-test';

    private const PATH = 'analysis/v1';

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(self::DISK);
        $this->createGreekVerse(16, '¶Οὕτως γὰρ ἠγάπησεν');
        $this->createGreekVerse(17, 'ἀγάπη ἐστίν');
    }

    public function test_it_imports_a_valid_artifact_and_skips_unchanged_rows(): void
    {
        $this->storeAnalysis($this->validAnalysis());

        $this->import()
            ->expectsOutputToContain('2 inserted')
            ->assertExitCode(0);

        $this->assertDatabaseCount('greek_verse_analyses', 2);

        $analysis = GreekVerseAnalysis::query()->where('gepi', 'JHN_3_16')->firstOrFail();

        $this->assertSame('OpenGNT', $analysis->greek_source);
        $this->assertSame('hu', $analysis->locale);
        $this->assertSame('úgy ugyanis', $analysis->analysis['segments'][0]['meaning']);
        $this->assertSame(self::PATH.'/JHN_3.json', $analysis->source_key);

        $originalUpdatedAt = $analysis->updated_at->toISOString();

        $this->travel(1)->second();

        $this->import()
            ->expectsOutputToContain('2 unchanged')
            ->assertExitCode(0);

        $this->assertSame(
            $originalUpdatedAt,
            $analysis->fresh()->updated_at->toISOString()
        );
    }

    public function test_it_imports_from_the_local_disk_and_standard_path_by_default(): void
    {
        Storage::fake('local');
        $this->storeAnalysis($this->validAnalysis(), 'local', self::DEFAULT_PATH);

        $this->artisan('szentiras:import-verse-analysis')
            ->expectsOutputToContain('2 inserted')
            ->assertExitCode(0);

        $this->assertDatabaseCount('greek_verse_analyses', 2);
        $this->assertDatabaseHas('greek_verse_analyses', [
            'gepi' => 'JHN_3_16',
            'source_key' => self::DEFAULT_PATH.'/JHN_3.json',
        ]);
    }

    public function test_it_imports_from_s3_when_the_disk_is_passed(): void
    {
        Storage::fake('s3');
        $this->storeAnalysis($this->validAnalysis(), 's3', self::DEFAULT_PATH);

        $this->artisan('szentiras:import-verse-analysis', ['--disk' => 's3'])
            ->expectsOutputToContain('2 inserted')
            ->assertExitCode(0);

        $this->assertDatabaseCount('greek_verse_analyses', 2);
    }

    public function test_an_invalid_artifact_aborts_without_writes(): void
    {
        $analysis = $this->validAnalysis();
        $analysis['verses'][0]['greekText'] = 'eltérő szöveg';
        $this->storeAnalysis($analysis);

        $this->import()
            ->expectsOutputToContain('Import aborted')
            ->assertExitCode(1);

        $this->assertDatabaseCount('greek_verse_analyses', 0);
    }

    public function test_dry_run_validates_without_writing(): void
    {
        $this->storeAnalysis($this->validAnalysis());

        $this->artisan('szentiras:import-verse-analysis', [
            '--disk' => self::DISK,
            '--path' => self::PATH,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run complete')
            ->assertExitCode(0);

        $this->assertDatabaseCount('greek_verse_analyses', 0);
    }

    public function test_prune_removes_only_missing_rows_below_the_import_path(): void
    {
        GreekVerseAnalysis::factory()->create([
            'gepi' => 'JHN_3_99',
            'source_key' => self::PATH.'/JHN_3.json',
        ]);
        GreekVerseAnalysis::factory()->create([
            'gepi' => 'MAT_1_1',
            'source_key' => 'another/path/MAT_1.json',
        ]);
        $this->storeAnalysis($this->validAnalysis());

        $this->artisan('szentiras:import-verse-analysis', [
            '--disk' => self::DISK,
            '--path' => self::PATH,
            '--prune' => true,
        ])
            ->expectsOutputToContain('1 pruned')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('greek_verse_analyses', ['gepi' => 'JHN_3_99']);
        $this->assertDatabaseHas('greek_verse_analyses', ['gepi' => 'MAT_1_1']);
    }

    private function createGreekVerse(int $verse, string $text): void
    {
        $greekVerse = new GreekVerse;
        $greekVerse->source = 'OpenGNT';
        $greekVerse->gepi = "JHN_3_{$verse}";
        $greekVerse->usx_code = 'JHN';
        $greekVerse->chapter = 3;
        $greekVerse->verse = $verse;
        $greekVerse->text = $text;
        $greekVerse->json = '[]';
        $greekVerse->strongs = '';
        $greekVerse->strong_transliterations = '';
        $greekVerse->strong_normalizations = '';
        $greekVerse->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function validAnalysis(): array
    {
        return [
            'format' => 1,
            'usxCode' => 'JHN',
            'book' => 'Jn',
            'chapter' => 3,
            'greekSource' => 'OpenGNT',
            'createdBy' => 'test-suite',
            'createdAt' => '2026-07-27',
            'verses' => [
                [
                    'verse' => 16,
                    'gepi' => 'JHN_3_16',
                    'greekText' => 'Οὕτως γὰρ ἠγάπησεν',
                    'segments' => [
                        [
                            'wordIndexes' => [0, 1],
                            'greek' => 'Οὕτως γὰρ',
                            'meaning' => 'úgy ugyanis',
                        ],
                        [
                            'wordIndexes' => [2],
                            'greek' => 'ἠγάπησεν',
                            'meaning' => 'szerette',
                            'alternatives' => ['megszerette'],
                        ],
                    ],
                ],
                [
                    'verse' => 17,
                    'gepi' => 'JHN_3_17',
                    'greekText' => 'ἀγάπη ἐστίν',
                    'segments' => [
                        [
                            'wordIndexes' => [0, 1],
                            'greek' => 'ἀγάπη ἐστίν',
                            'meaning' => 'szeretet van',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function storeAnalysis(
        array $analysis,
        string $disk = self::DISK,
        string $path = self::PATH,
    ): void {
        Storage::disk($disk)->put(
            $path.'/JHN_3.json',
            json_encode(
                $analysis,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    private function import(): PendingCommand
    {
        return $this->artisan('szentiras:import-verse-analysis', [
            '--disk' => self::DISK,
            '--path' => self::PATH,
        ]);
    }
}
