<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\GreekVerseAnalysis;
use SzentirasHu\Test\Common\TestCase;

class GreekVerseAnalysisDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const GREEK_TRANSLATION_ID = 7;

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    public function test_chapter_page_renders_a_lazy_toggle_without_the_analysis_payload(): void
    {
        $this->createGreekBook();
        $verse = $this->createGreekVerse(1, 'λόγος');
        $this->createGreekVerse(2, 'ἀγάπη');
        $this->createAnalysis($verse);

        $response = $this->get('/GNT/Mt1');

        $response->assertOk();
        $response->assertSee('verse-analysis-toggle', false);
        $response->assertSee('/GNT/verse-analysis/MAT_1_1?layout=inline-v1', false);
        $response->assertSee('id="greek-verse-text-MAT_1_1"', false);
        $response->assertSee('data-replaces="greek-verse-text-MAT_1_1"', false);
        $response->assertDontSeeText('szövegösszefüggés szerinti jelentés');
        $this->assertSame(1, substr_count($response->getContent(), 'verse-analysis-toggle'));
        $this->assertMatchesRegularExpression(
            '/data-link="GNT\/Mt1,1".*?verse-analysis-toggle.*?id="greek-verse-text-MAT_1_1"/s',
            $response->getContent(),
        );
    }

    public function test_endpoint_returns_the_rendered_analysis_with_cache_headers(): void
    {
        $verse = $this->createGreekVerse(1, 'λόγος ζωῆς');
        $this->createAnalysis($verse);

        $response = $this->get('/GNT/verse-analysis/MAT_1_1');

        $response->assertOk();
        $response->assertSeeText('szövegösszefüggés szerinti jelentés');
        $response->assertSeeText('(kijelentés)');
        $response->assertSeeText('A mondat alanya.');
        $response->assertSee('verse-analysis-text', false);
        $response->assertSee('verse-analysis-segment', false);
        $response->assertSee('class="greekWord clickable-greek"', false);
        $response->assertSee('data-usx="MAT"', false);
        $response->assertSee('data-chapter="1"', false);
        $response->assertSee('data-verse="1"', false);
        $response->assertSee('data-i="0"', false);
        $response->assertSee('data-i="1"', false);
        $response->assertSee('[font-family:var(--book-font)]', false);
        $response->assertDontSee('[font-family:var(--text-font)]', false);
        $response->assertSee('[font-size:calc(var(--book-font-size)*0.75)] italic', false);
        $response->assertDontSeeText('másképp:');
        $response->assertDontSee('bi-arrow-right', false);
        $response->assertDontSee('<div', false);
        $renderedText = html_entity_decode(strip_tags($response->getContent()));
        $this->assertStringNotContainsString('–', $renderedText);
        $this->assertStringNotContainsString(';', $renderedText);
        $etag = hash('sha256', $response->getContent());
        $response->assertHeader('ETag', '"'.$etag.'"');
        $this->assertStringContainsString('max-age=0', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));

        $this->withHeader('If-None-Match', '"'.$etag.'"')
            ->get('/GNT/verse-analysis/MAT_1_1')
            ->assertStatus(304);
    }

    public function test_endpoint_moves_a_trailing_verse_break_after_the_analysis(): void
    {
        $verse = $this->createGreekVerse(1, 'γενεαὶ δεκατέσσαρες.¶');
        $this->createAnalysis($verse);

        $response = $this->get('/GNT/verse-analysis/MAT_1_1');

        $response->assertOk();
        $response->assertSeeText('δεκατέσσαρες.');
        $response->assertSeeText('szövegösszefüggés szerinti jelentés');
        $this->assertSame(1, substr_count($response->getContent(), '<br>'));
        $this->assertGreaterThan(
            strpos($response->getContent(), 'A mondat alanya.'),
            strpos($response->getContent(), '<br>'),
        );
    }

    public function test_endpoint_does_not_return_an_analysis_for_another_greek_source(): void
    {
        $verse = $this->createGreekVerse(1, 'λόγος');
        $this->createAnalysis($verse, 'BMT');

        $this->get('/GNT/verse-analysis/MAT_1_1')->assertNotFound();
    }

    private function createGreekBook(): void
    {
        $book = new Book;
        $book->translation_id = self::GREEK_TRANSLATION_ID;
        $book->name = 'Evangélium Máté szerint';
        $book->abbrev = 'Mt';
        $book->link = 'Mt';
        $book->old_testament = 0;
        $book->order = 1;
        $book->usx_code = 'MAT';
        $book->save();
    }

    private function createGreekVerse(int $verseNumber, string $text): GreekVerse
    {
        $verse = new GreekVerse;
        $verse->source = 'OpenGNT';
        $verse->gepi = "MAT_1_{$verseNumber}";
        $verse->usx_code = 'MAT';
        $verse->chapter = 1;
        $verse->verse = $verseNumber;
        $verse->text = $text;
        $verse->json = '[]';
        $verse->strongs = '';
        $verse->strong_transliterations = '';
        $verse->strong_normalizations = '';
        $verse->save();

        return $verse;
    }

    private function createAnalysis(
        GreekVerse $verse,
        string $greekSource = 'OpenGNT',
    ): GreekVerseAnalysis {
        $payload = [
            'verse' => $verse->verse,
            'gepi' => $verse->gepi,
            'greekText' => $verse->text,
            'segments' => [
                [
                    'wordIndexes' => array_keys(explode(' ', str_replace('¶', '', $verse->text))),
                    'greek' => $verse->text,
                    'meaning' => 'szövegösszefüggés szerinti jelentés',
                    'alternatives' => ['kijelentés'],
                    'note' => 'A mondat alanya.',
                ],
            ],
        ];

        return GreekVerseAnalysis::factory()->create([
            'gepi' => $verse->gepi,
            'greek_source' => $greekSource,
            'analysis' => $payload,
            'content_hash' => hash(
                'sha256',
                json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            ),
        ]);
    }
}
