<?php

namespace SzentirasHu\Test\Mcp;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Mcp\Servers\BibleServer;
use SzentirasHu\Mcp\Tools\GetGreekVersesTool;
use SzentirasHu\Mcp\Tools\LookupGreekWordTool;
use SzentirasHu\Models\DictionaryEntry;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The Greek tools exist so that an agent reasons about the original wording from the actual
 * text rather than from memory. These tests pin down that a Hungarian reference finds the
 * right Greek verses, that every word carries the data needed to look it up, and that
 * anything outside the Greek New Testament fails loudly instead of returning nothing.
 */
class GreekMcpToolTest extends FastDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * @param  array<int, array{strong: string, morphology: string}>  $analysis
     */
    private function createGreekVerse(
        int $chapter,
        int $verse,
        string $text,
        string $lemmas,
        string $transliterations,
        array $analysis = [],
        string $usxCode = 'JHN'
    ): GreekVerse {
        $greekVerse = new GreekVerse();
        $greekVerse->source = 'test';
        $greekVerse->gepi = "{$usxCode}_{$chapter}_{$verse}";
        $greekVerse->usx_code = $usxCode;
        $greekVerse->chapter = $chapter;
        $greekVerse->verse = $verse;
        $greekVerse->text = $text;
        $greekVerse->json = json_encode($analysis);
        $greekVerse->strongs = $lemmas;
        $greekVerse->strong_transliterations = $transliterations;
        $greekVerse->strong_normalizations = '';
        $greekVerse->save();

        return $greekVerse;
    }

    /**
     * John 3 in miniature: three verses, the first one fully analysed.
     */
    private function createJohnChapterThree(): void
    {
        // The leading paragraph marker must not leak into the returned text.
        $this->createGreekVerse(3, 16, '¶Οὕτως γὰρ ἠγάπησεν', 'οὕτω γάρ ἀγαπάω', 'houtó gar agapaó', [
            ['strong' => '3779', 'morphology' => 'ADV'],
            ['strong' => '1063', 'morphology' => 'CONJ'],
            ['strong' => '25', 'morphology' => 'V-AAI-3S'],
        ]);

        $this->createGreekVerse(3, 17, 'ἀγάπη ἐστίν', 'ἀγάπη εἰμί', 'agapé estin', [
            ['strong' => '26', 'morphology' => 'N-NSF'],
            ['strong' => '1510', 'morphology' => 'V-PAI-3S'],
        ]);

        $this->createGreekVerse(3, 18, 'πιστεύων', 'πιστεύω', 'pisteuó', [
            ['strong' => '4100', 'morphology' => 'V-PAP-NSM'],
        ]);
    }

    private function createStrongWord(int $number, string $lemma, string $transliteration): StrongWord
    {
        $strongWord = new StrongWord();
        $strongWord->number = $number;
        $strongWord->lemma = $lemma;
        $strongWord->transliteration = $transliteration;
        $strongWord->normalized = $transliteration;
        $strongWord->save();

        return $strongWord;
    }

    private function createDictionaryMeaning(int $strongWordNumber, int $order, string $meaning, string $explanation = 'magyarázat'): void
    {
        $dictionaryMeaning = new DictionaryMeaning();
        $dictionaryMeaning->strong_word_number = $strongWordNumber;
        $dictionaryMeaning->order = $order;
        $dictionaryMeaning->meaning = $meaning;
        $dictionaryMeaning->explanation = $explanation;
        $dictionaryMeaning->source = 'test';
        $dictionaryMeaning->save();
    }

    private function createAgapeDictionary(): StrongWord
    {
        $strongWord = $this->createStrongWord(26, 'ἀγάπη', 'agapé');

        $dictionaryEntry = new DictionaryEntry();
        $dictionaryEntry->strong_word_number = 26;
        $dictionaryEntry->paradigm = 'ἀγάπη, -ης, ἡ';
        $dictionaryEntry->etymology = 'az ἀγαπάω igéből';
        $dictionaryEntry->notes = 'újszövetségi kulcsszó';
        $dictionaryEntry->source = 'test';
        $dictionaryEntry->save();

        // Stored out of order on purpose: the response must lead with the lowest order.
        $this->createDictionaryMeaning(26, 2, 'szeretetvendégség');
        $this->createDictionaryMeaning(26, 1, 'szeretet');

        return $strongWord;
    }

    public function test_returns_the_greek_text_of_a_verse(): void
    {
        $this->createJohnChapterThree();

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Jn 3,16'])
            ->assertOk()
            ->assertSee('"reference":"Jn 3,16"')
            ->assertSee('"gepi":"JHN_3_16"')
            // The paragraph marker is stripped from the returned text.
            ->assertSee('"greekText":"Οὕτως γὰρ ἠγάπησεν"');
    }

    public function test_words_carry_lemma_transliteration_strong_number_and_morphology(): void
    {
        $this->createJohnChapterThree();

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Jn 3,16'])
            ->assertOk()
            ->assertSee('"printed":"ἠγάπησεν"')
            ->assertSee('"lemma":"ἀγαπάω"')
            ->assertSee('"transliteration":"agapaó"')
            ->assertSee('"strongNumber":25')
            ->assertSee('"morphology":"V-AAI-3S"')
            ->assertSee('"morphologyDescription":"ige, aorisztosz, cselekvő, kijelentő, egyes szám, harmadik személy"');
    }

    public function test_words_carry_the_first_hungarian_meaning(): void
    {
        $this->createJohnChapterThree();
        $this->createDictionaryMeaning(25, 2, 'megszeret');
        $this->createDictionaryMeaning(25, 1, 'szeret');

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Jn 3,16'])
            ->assertOk()
            // The meaning with the lowest order wins.
            ->assertSee('"meaning":"szeret"')
            ->assertDontSee('"meaning":"megszeret"');
    }

    public function test_words_without_a_known_meaning_report_null(): void
    {
        $this->createJohnChapterThree();

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Jn 3,16'])
            ->assertOk()
            ->assertSee('"strongNumber":1063')
            ->assertSee('"meaning":null');
    }

    public function test_verse_range_returns_only_the_requested_verses(): void
    {
        $this->createJohnChapterThree();

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Jn 3,16-17'])
            ->assertOk()
            ->assertSee('"reference":"Jn 3,16"')
            ->assertSee('"reference":"Jn 3,17"')
            ->assertDontSee('"reference":"Jn 3,18"');
    }

    public function test_chapter_reference_returns_the_whole_chapter(): void
    {
        $this->createJohnChapterThree();

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Jn 3'])
            ->assertOk()
            ->assertSee(['"reference":"Jn 3,16"', '"reference":"Jn 3,17"', '"reference":"Jn 3,18"']);
    }

    public function test_verse_without_stored_analysis_still_returns_its_words(): void
    {
        // Older imports carry no per-word analysis; the printed text must still come through.
        $this->createGreekVerse(1, 1, 'Βίβλος γενέσεως', 'βίβλος γένεσις', 'biblos genesis', [], 'MAT');

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Mt 1,1'])
            ->assertOk()
            ->assertSee('"printed":"Βίβλος"')
            ->assertSee('"lemma":"βίβλος"')
            ->assertSee('"strongNumber":null')
            ->assertSee('"morphologyDescription":""');
    }

    public function test_malformed_reference_returns_a_clear_error(): void
    {
        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'nonsense!!'])
            ->assertHasErrors()
            ->assertSee('Could not parse the reference');
    }

    public function test_reference_outside_the_greek_new_testament_errors(): void
    {
        $this->createJohnChapterThree();

        // Genesis has no Greek text here, and must not silently return an empty result.
        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Ter 1,1'])
            ->assertHasErrors()
            ->assertSee('Only the New Testament exists in Greek');
    }

    public function test_missing_greek_verse_errors_instead_of_returning_nothing(): void
    {
        $this->createJohnChapterThree();

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Jn 99,1'])
            ->assertHasErrors()
            ->assertSee('No Greek text found');
    }

    public function test_response_names_the_greek_text_and_its_licence(): void
    {
        $this->createJohnChapterThree();

        BibleServer::tool(GetGreekVersesTool::class, ['reference' => 'Jn 3,16'])
            ->assertOk()
            ->assertSee('"abbrev":"GNT"')
            ->assertSee('OpenGNT');
    }

    public function test_lookup_returns_the_dictionary_entry(): void
    {
        $this->createAgapeDictionary();

        BibleServer::tool(LookupGreekWordTool::class, ['strongNumber' => 26])
            ->assertOk()
            ->assertSee('"strongNumber":26')
            ->assertSee('"lemma":"ἀγάπη"')
            ->assertSee('"transliteration":"agapé"')
            ->assertSee('"paradigm":"ἀγάπη, -ης, ἡ"')
            ->assertSee('"etymology":"az ἀγαπάω igéből"')
            ->assertSee('"notes":"újszövetségi kulcsszó"');
    }

    public function test_lookup_lists_meanings_most_common_first(): void
    {
        $this->createAgapeDictionary();

        BibleServer::tool(LookupGreekWordTool::class, ['strongNumber' => 26])
            ->assertOk()
            ->assertSee('"meanings":[{"meaning":"szeretet"');
    }

    public function test_lookup_counts_the_verses_the_word_occurs_in(): void
    {
        $this->createJohnChapterThree();
        $strongWord = $this->createAgapeDictionary();
        $strongWord->greekVerses()->attach(
            GreekVerse::where('gepi', 'JHN_3_17')->firstOrFail()->id,
            ['position' => 0]
        );

        BibleServer::tool(LookupGreekWordTool::class, ['strongNumber' => 26])
            ->assertOk()
            ->assertSee('"verseOccurrences":1');
    }

    public function test_lookup_of_an_unknown_strong_number_errors(): void
    {
        BibleServer::tool(LookupGreekWordTool::class, ['strongNumber' => 999999])
            ->assertHasErrors()
            ->assertSee('No Greek word found for Strong number 999999');
    }

    public function test_greek_tools_are_advertised_next_to_the_hungarian_ones(): void
    {
        // Registration matters over the real transport: a tool the server does not list
        // cannot be discovered by a client, however well it works when called directly.
        $response = $this->postJson('/mcp/bible', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ], ['Accept' => 'application/json, text/event-stream']);

        $response->assertOk();

        $toolNames = collect($response->json('result.tools'))->pluck('name')->all();

        $this->assertContains('get-verses', $toolNames);
        $this->assertContains('get-greek-verses', $toolNames);
        $this->assertContains('lookup-greek-word', $toolNames);
    }
}
