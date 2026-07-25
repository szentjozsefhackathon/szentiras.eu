<?php

namespace SzentirasHu\Test;

use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The per chapter verse analysis files are written by agents, so nothing but this validator
 * stands between a hand-curated chapter and a later import. These tests pin down that the
 * validator rejects exactly the mistakes that would make a file unimportable: a missing verse,
 * a word index left out or covered twice, a segment whose Greek does not match the printed
 * words, and a Greek text that drifted from the database.
 */
class ValidateVerseAnalysisTest extends FastDatabaseTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/verse-analysis-'.uniqid();
        mkdir($this->directory);

        $this->createJohnChapterThree();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*.json') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    /**
     * Two short verses of John 3, the first one carrying a paragraph marker that must not
     * appear in the analysis file.
     */
    private function createJohnChapterThree(): void
    {
        $this->createGreekVerse(16, '¶Οὕτως γὰρ ἠγάπησεν');
        $this->createGreekVerse(17, 'ἀγάπη ἐστίν');
    }

    /**
     * A verse whose stored text ends with GREEK ANO TELEIA (U+0387) and contains a GREEK
     * QUESTION MARK (U+037E), the two punctuation marks that NFC normalisation rewrites to
     * their canonically equivalent MIDDLE DOT (U+00B7) and SEMICOLON (U+003B).
     */
    private function createGreekVerseWithGreekPunctuation(): void
    {
        $this->createGreekVerse(18, "τίς εἶ\u{037E} ἐγώ εἰμι\u{0387}");
    }

    private function createGreekVerse(int $verse, string $text): void
    {
        $greekVerse = new GreekVerse();
        $greekVerse->source = 'test';
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
            'verses' => [
                [
                    'verse' => 16,
                    'gepi' => 'JHN_3_16',
                    'greekText' => 'Οὕτως γὰρ ἠγάπησεν',
                    'segments' => [
                        ['wordIndexes' => [0, 1], 'greek' => 'Οὕτως γὰρ', 'meaning' => 'úgy ugyanis'],
                        ['wordIndexes' => [2], 'greek' => 'ἠγάπησεν', 'meaning' => 'szerette', 'alternatives' => ['megszerette']],
                    ],
                ],
                [
                    'verse' => 17,
                    'gepi' => 'JHN_3_17',
                    'greekText' => 'ἀγάπη ἐστίν',
                    'segments' => [
                        ['wordIndexes' => [0, 1], 'greek' => 'ἀγάπη ἐστίν', 'meaning' => 'szeretet van'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function writeAnalysis(array $analysis): void
    {
        file_put_contents(
            $this->directory.'/JHN_3.json',
            json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private function validate(string $reference = 'Jn 3'): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('szentiras:validate-verse-analysis', [
            'reference' => $reference,
            '--dir' => $this->directory,
        ]);
    }

    public function test_a_valid_file_passes(): void
    {
        $this->writeAnalysis($this->validAnalysis());

        $this->validate()
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    }

    public function test_a_missing_file_fails(): void
    {
        $this->validate()
            ->expectsOutputToContain('The analysis file does not exist.')
            ->assertExitCode(1);
    }

    public function test_invalid_json_fails(): void
    {
        file_put_contents($this->directory.'/JHN_3.json', '{ not json');

        $this->validate()
            ->expectsOutputToContain('not valid JSON')
            ->assertExitCode(1);
    }

    public function test_a_verse_missing_from_the_file_fails(): void
    {
        $analysis = $this->validAnalysis();
        array_pop($analysis['verses']);

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('do not match the verses of the chapter')
            ->assertExitCode(1);
    }

    public function test_a_word_index_left_out_fails(): void
    {
        $analysis = $this->validAnalysis();
        $analysis['verses'][0]['segments'][0] = ['wordIndexes' => [0], 'greek' => 'Οὕτως', 'meaning' => 'úgy'];

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('do not cover the word indexes 1')
            ->assertExitCode(1);
    }

    public function test_a_word_index_covered_twice_fails(): void
    {
        $analysis = $this->validAnalysis();
        $analysis['verses'][0]['segments'][1] = ['wordIndexes' => [1, 2], 'greek' => 'γὰρ ἠγάπησεν', 'meaning' => 'ugyanis szerette'];

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('is covered by more than one segment')
            ->assertExitCode(1);
    }

    public function test_a_word_index_outside_the_verse_fails(): void
    {
        $analysis = $this->validAnalysis();
        $analysis['verses'][1]['segments'][0] = ['wordIndexes' => [0, 1, 2], 'greek' => 'ἀγάπη ἐστίν', 'meaning' => 'szeretet van'];

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('is outside the verse')
            ->assertExitCode(1);
    }

    public function test_a_segment_greek_that_differs_from_the_printed_words_fails(): void
    {
        $analysis = $this->validAnalysis();
        $analysis['verses'][0]['segments'][0]['greek'] = 'Οὕτως γάρ';

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('the "greek" field must be "Οὕτως γὰρ"')
            ->assertExitCode(1);
    }

    public function test_a_greek_text_that_differs_from_the_database_fails(): void
    {
        $analysis = $this->validAnalysis();
        // The paragraph marker belongs to the stored text only, never to the analysis file.
        $analysis['verses'][0]['greekText'] = '¶Οὕτως γὰρ ἠγάπησεν';

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('the "greekText" field differs')
            ->assertExitCode(1);
    }

    public function test_greek_punctuation_confusables_are_accepted(): void
    {
        $this->createGreekVerseWithGreekPunctuation();

        $analysis = $this->validAnalysis();
        // The file carries the NFC forms (MIDDLE DOT, SEMICOLON) that a writing tool emits,
        // while the database stores GREEK ANO TELEIA and GREEK QUESTION MARK. They are the same
        // characters, so validation must accept the file.
        $analysis['verses'][] = [
            'verse' => 18,
            'gepi' => 'JHN_3_18',
            'greekText' => "τίς εἶ\u{003B} ἐγώ εἰμι\u{00B7}",
            'segments' => [
                ['wordIndexes' => [0, 1], 'greek' => "τίς εἶ\u{003B}", 'meaning' => 'ki vagy'],
                ['wordIndexes' => [2, 3], 'greek' => "ἐγώ εἰμι\u{00B7}", 'meaning' => 'én vagyok'],
            ],
        ];

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    }

    public function test_an_empty_meaning_fails(): void
    {
        $analysis = $this->validAnalysis();
        $analysis['verses'][0]['segments'][0]['meaning'] = '  ';

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('the "meaning" field is missing or empty')
            ->assertExitCode(1);
    }

    public function test_a_wrong_chapter_header_fails(): void
    {
        $analysis = $this->validAnalysis();
        $analysis['chapter'] = 4;

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('The "chapter" field must be 3.')
            ->assertExitCode(1);
    }

    public function test_non_contiguous_word_indexes_are_only_a_warning(): void
    {
        $analysis = $this->validAnalysis();
        $analysis['verses'][0]['segments'] = [
            ['wordIndexes' => [0, 2], 'greek' => 'Οὕτως ἠγάπησεν', 'meaning' => 'úgy szerette'],
            ['wordIndexes' => [1], 'greek' => 'γὰρ', 'meaning' => 'ugyanis'],
        ];

        $this->writeAnalysis($analysis);

        $this->validate()
            ->expectsOutputToContain('not contiguous')
            ->assertExitCode(0);
    }

    public function test_missing_lists_the_chapters_without_a_file(): void
    {
        $this->artisan('szentiras:validate-verse-analysis', [
            'reference' => 'Jn 3',
            '--dir' => $this->directory,
            '--missing' => true,
        ])
            ->expectsOutputToContain('JHN_3')
            ->expectsOutputToContain('1 chapter(s) missing out of 1.')
            ->assertExitCode(0);
    }

    public function test_missing_lists_nothing_once_the_file_exists(): void
    {
        $this->writeAnalysis($this->validAnalysis());

        $this->artisan('szentiras:validate-verse-analysis', [
            'reference' => 'Jn 3',
            '--dir' => $this->directory,
            '--missing' => true,
        ])
            ->expectsOutputToContain('0 chapter(s) missing out of 1.')
            ->assertExitCode(0);
    }

    public function test_an_unparsable_reference_fails(): void
    {
        $this->artisan('szentiras:validate-verse-analysis', [
            'reference' => '???',
            '--dir' => $this->directory,
        ])
            ->expectsOutputToContain('Could not parse the reference')
            ->assertExitCode(1);
    }
}
