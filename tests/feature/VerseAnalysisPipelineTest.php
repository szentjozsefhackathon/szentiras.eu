<?php

namespace SzentirasHu\Test;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class VerseAnalysisPipelineTest extends FastDatabaseTestCase
{
    private string $outputDirectory;

    private string $workRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $temporaryRoot = sys_get_temp_dir().'/verse-analysis-pipeline-'.uniqid();
        $this->workRoot = $temporaryRoot.'/work';
        $this->outputDirectory = $temporaryRoot.'/output';
        mkdir($this->workRoot, 0775, true);
        mkdir($this->outputDirectory, 0775, true);

        $this->createTemplateBook();
        $this->createHungarianChapter();
        $this->createGreekChapter();
        $this->createDictionaryMeanings();

        Translation::query()->whereKey(1001)->update(['denom' => 'katolikus']);
        config(['settings.enabledTranslations' => [1001]]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $temporaryRoot = dirname($this->workRoot);

        if (is_dir($temporaryRoot)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $temporaryRoot,
                    \FilesystemIterator::SKIP_DOTS,
                ),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    rmdir($item->getPathname());
                } else {
                    unlink($item->getPathname());
                }
            }

            rmdir($temporaryRoot);
        }

        parent::tearDown();
    }

    public function test_exporter_writes_compact_word_chunks_with_context(): void
    {
        $this->artisan('szentiras:export-verse-analysis-context', [
            'reference' => 'Jn 3',
            '--dir' => $this->workRoot,
            '--chunk-words' => 4,
            '--translations' => 'TESTTRANS',
        ])->assertExitCode(0);

        $manifest = $this->readJson($this->manifestPath());

        $this->assertSame('JHN_3', $manifest['chapterKey']);
        $this->assertCount(2, $manifest['chunks']);
        $this->assertSame([16], $manifest['chunks'][0]['verses']);
        $this->assertSame([17, 18], $manifest['chunks'][1]['verses']);

        $firstSource = $this->readJson(
            dirname($this->manifestPath()).'/'.$manifest['chunks'][0]['source']
        );

        $this->assertSame(
            ['printed', 'strongNumber', 'morphology'],
            $firstSource['wordTuple'],
        );
        $this->assertSame(
            ['Οὕτως', 3779, 'ADV'],
            $firstSource['verses'][0]['words'][0],
        );
        $this->assertSame(['úgy'], $firstSource['dictionary']['3779']);
        $this->assertSame('határozószó', $firstSource['morphology']['ADV']);
        $this->assertArrayNotHasKey('greekText', $firstSource['verses'][0]);
        $this->assertSame(17, $firstSource['contextAfter']['verse']);
    }

    public function test_assembler_reconstructs_exact_greek_fields_and_produces_a_valid_chapter(): void
    {
        $manifest = $this->exportAndWriteValidSemantics();

        $this->artisan('szentiras:assemble-verse-analysis', [
            'manifest' => $this->manifestPath(),
            '--output-dir' => $this->outputDirectory,
            '--created-by' => 'test-semantic-model',
        ])->assertExitCode(0);

        $outputPath = $this->outputDirectory.'/JHN_3.json';
        $analysis = $this->readJson($outputPath);

        $this->assertSame('test-semantic-model', $analysis['createdBy']);
        $this->assertSame('Οὕτως γὰρ ἠγάπησεν', $analysis['verses'][0]['greekText']);
        $this->assertSame('Οὕτως', $analysis['verses'][0]['segments'][0]['greek']);
        $this->assertSame(
            'γὰρ ἠγάπησεν',
            $analysis['verses'][0]['segments'][1]['greek'],
        );
        $this->assertSame(
            ['mert szerette'],
            $analysis['verses'][0]['segments'][1]['alternatives'],
        );
        $this->assertCount(3, $analysis['verses']);

        $this->artisan('szentiras:validate-verse-analysis', [
            'reference' => 'Jn 3',
            '--dir' => $this->outputDirectory,
        ])
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);

        $this->assertCount(2, $manifest['chunks']);
    }

    public function test_exporter_reuses_valid_checkpoints_and_removes_invalid_ones(): void
    {
        $manifest = $this->exportAndWriteValidSemantics();
        $chapterDirectory = dirname($this->manifestPath());
        $validSemanticPath = $chapterDirectory.'/'.$manifest['chunks'][0]['semantic'];
        $invalidSemanticPath = $chapterDirectory.'/'.$manifest['chunks'][1]['semantic'];
        $validSemantic = (string) file_get_contents($validSemanticPath);
        $invalidSemantic = $this->readJson($invalidSemanticPath);
        array_pop($invalidSemantic['verses'][0]['segments']);
        $this->writeJson($invalidSemanticPath, $invalidSemantic);

        $this->artisan('szentiras:export-verse-analysis-context', [
            'reference' => 'Jn 3',
            '--dir' => $this->workRoot,
            '--chunk-words' => 4,
            '--translations' => 'TESTTRANS',
        ])->assertExitCode(0);

        $this->assertSame($validSemantic, file_get_contents($validSemanticPath));
        $this->assertFileDoesNotExist($invalidSemanticPath);
    }

    public function test_assembler_rejects_a_semantic_chunk_that_does_not_cover_every_word(): void
    {
        $manifest = $this->exportAndWriteValidSemantics();
        $semanticPath = dirname($this->manifestPath()).'/'.$manifest['chunks'][0]['semantic'];
        $semantic = $this->readJson($semanticPath);
        $semantic['verses'][0]['segments'] = [
            ['wordIndexes' => [0], 'meaning' => 'úgy'],
        ];
        $this->writeJson($semanticPath, $semantic);

        $this->artisan('szentiras:assemble-verse-analysis', [
            'manifest' => $this->manifestPath(),
            '--output-dir' => $this->outputDirectory,
        ])
            ->expectsOutputToContain('does not cover word indexes')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($this->outputDirectory.'/JHN_3.json');
    }

    /**
     * @return array<string, mixed>
     */
    private function exportAndWriteValidSemantics(): array
    {
        $this->artisan('szentiras:export-verse-analysis-context', [
            'reference' => 'Jn 3',
            '--dir' => $this->workRoot,
            '--chunk-words' => 4,
            '--translations' => 'TESTTRANS',
        ])->assertExitCode(0);

        $manifest = $this->readJson($this->manifestPath());
        $chapterDirectory = dirname($this->manifestPath());

        foreach ($manifest['chunks'] as $chunk) {
            $source = $this->readJson($chapterDirectory.'/'.$chunk['source']);
            $semanticVerses = [];

            foreach ($source['verses'] as $sourceVerse) {
                $segments = [];

                foreach ($sourceVerse['words'] as $wordIndex => $word) {
                    $segments[] = [
                        'wordIndexes' => [$wordIndex],
                        'meaning' => "jelentés {$sourceVerse['verse']}/{$wordIndex}",
                    ];
                }

                if ($sourceVerse['verse'] === 16) {
                    $segments = [
                        [
                            'wordIndexes' => [0],
                            'meaning' => 'úgy',
                        ],
                        [
                            'wordIndexes' => [1, 2],
                            'meaning' => 'ugyanis szerette',
                            'alternatives' => ['mert szerette'],
                        ],
                    ];
                }

                $semanticVerses[] = [
                    'verse' => $sourceVerse['verse'],
                    'segments' => $segments,
                ];
            }

            $this->writeJson(
                $chapterDirectory.'/'.$chunk['semantic'],
                ['verses' => $semanticVerses],
            );
        }

        return $manifest;
    }

    private function createTemplateBook(): void
    {
        $translation = Translation::query()->find(7);

        if ($translation === null) {
            $translation = new Translation;
            $translation->id = 7;
            $translation->name = 'Template translation';
            $translation->abbrev = 'STL';
            $translation->denom = 'katolikus';
            $translation->lang = 'hu';
            $translation->save();
        }

        if (
            Book::query()
                ->where('translation_id', $translation->id)
                ->where('usx_code', 'JHN')
                ->doesntExist()
        ) {
            $book = new Book;
            $book->name = 'János';
            $book->abbrev = 'Jn';
            $book->link = 'jn';
            $book->old_testament = 0;
            $book->order = 43;
            $book->usx_code = 'JHN';
            $book->translation()->associate($translation);
            $book->save();
        }
    }

    private function createHungarianChapter(): void
    {
        $translation = Translation::query()->findOrFail(1001);
        $book = new Book;
        $book->name = 'János';
        $book->abbrev = 'Jn';
        $book->link = 'jn';
        $book->old_testament = 0;
        $book->order = 43;
        $book->usx_code = 'JHN';
        $book->translation()->associate($translation);
        $book->save();

        foreach ([16 => 'Mert úgy szerette.', 17 => 'A szeretet van.', 18 => 'Ő igaz.'] as $number => $text) {
            $verse = new Verse;
            $verse->trans = $translation->id;
            $verse->gepi = "JHN_3_{$number}";
            $verse->usx_code = 'JHN';
            $verse->book_id = $book->id;
            $verse->chapter = 3;
            $verse->numv = $number;
            $verse->tip = 6;
            $verse->verse = $text;
            $verse->verseroot = $text;
            $verse->ido = '2026-07-27';
            $verse->save();
        }
    }

    private function createGreekChapter(): void
    {
        $this->createGreekVerse(
            16,
            '¶Οὕτως γὰρ ἠγάπησεν',
            [
                ['strong' => 3779, 'morphology' => 'ADV'],
                ['strong' => 1063, 'morphology' => 'CONJ'],
                ['strong' => 25, 'morphology' => 'V-AAI-3S'],
            ],
        );
        $this->createGreekVerse(
            17,
            'ἀγάπη ἐστίν',
            [
                ['strong' => 26, 'morphology' => 'N-NSF'],
                ['strong' => 1510, 'morphology' => 'V-PAI-3S'],
            ],
        );
        $this->createGreekVerse(
            18,
            'αὐτὸς δίκαιος',
            [
                ['strong' => 846, 'morphology' => 'P-NSM'],
                ['strong' => 1342, 'morphology' => 'A-NSM'],
            ],
        );
    }

    /**
     * @param  array<int, array{strong: int, morphology: string}>  $analysis
     */
    private function createGreekVerse(int $number, string $text, array $analysis): void
    {
        $verse = new GreekVerse;
        $verse->source = 'OpenGNT';
        $verse->gepi = "JHN_3_{$number}";
        $verse->usx_code = 'JHN';
        $verse->chapter = 3;
        $verse->verse = $number;
        $verse->text = $text;
        $verse->json = json_encode($analysis, JSON_THROW_ON_ERROR);
        $verse->strongs = implode(' ', array_fill(0, count($analysis), 'lemma'));
        $verse->strong_transliterations = implode(' ', array_fill(0, count($analysis), 'translit'));
        $verse->strong_normalizations = '';
        $verse->save();
    }

    private function createDictionaryMeanings(): void
    {
        foreach (
            [
                25 => ['szeret'],
                26 => ['szeretet'],
                846 => ['ő'],
                1063 => ['ugyanis', 'mert'],
                1342 => ['igaz'],
                1510 => ['van'],
                3779 => ['úgy'],
            ] as $strongNumber => $meanings
        ) {
            foreach ($meanings as $order => $meaning) {
                $dictionaryMeaning = new DictionaryMeaning;
                $dictionaryMeaning->strong_word_number = $strongNumber;
                $dictionaryMeaning->order = $order;
                $dictionaryMeaning->meaning = $meaning;
                $dictionaryMeaning->explanation = '';
                $dictionaryMeaning->source = 'test';
                $dictionaryMeaning->save();
            }
        }
    }

    private function manifestPath(): string
    {
        return $this->workRoot.'/JHN_3/manifest.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        return json_decode(
            (string) file_get_contents($path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function writeJson(string $path, array $value): void
    {
        file_put_contents(
            $path,
            json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            )."\n",
        );
    }
}
