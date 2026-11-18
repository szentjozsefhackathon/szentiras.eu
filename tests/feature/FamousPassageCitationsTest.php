<?php

namespace SzentirasHu\Test;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The citations are collected from Magister's /api/kerdes, one source group at a time, and stored
 * with the passage; the page quotes them without asking Magister.
 */
class FamousPassageCitationsTest extends FastDatabaseTestCase
{
    private const MAGISTER_URL = 'https://magister.test';

    private const MAGISTER_PUBLIC_URL = 'https://magister.public.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.magister.url' => self::MAGISTER_URL, 'services.magister.public_url' => self::MAGISTER_PUBLIC_URL]);
    }

    public function test_command_stores_the_quotations_of_each_source_group(): void
    {
        $passage = FamousPassage::factory()->create();
        $this->fakeMagister([
            'Ter 2' => [
                'KEK' => $this->answer(2, [
                    $this->unit('KEK 2184', 'A pihenőnapról szól. Isten „a hetedik napon megpihent” (Ter 2,2), és ezt a napot megszentelte.', 'Ter 2,2'),
                    $this->unit('KEK 345', 'A szombat a hat nap művének befejezése: „Isten megáldotta a hetedik napot és megszentelte” (Ter 2,3).', 'Ter 2,3'),
                ]),
                'zsinat' => ['question' => 'Ter 2', 'units' => []],
            ],
        ]);

        $this->artisan('szentiras:famous-passage-citations')
            ->expectsOutput('a-hetedik-nap (Ter 2): KEK 2/2')
            ->assertSuccessful();

        $passage->refresh();
        $this->assertNotNull($passage->church_citations_fetched_at);
        $this->assertCount(1, $passage->church_citations);
        $catechism = $passage->church_citations[0];
        $this->assertSame('KEK', $catechism['key']);
        $this->assertSame('A Katolikus Egyház Katekizmusa', $catechism['title']);
        $this->assertSame(['KEK 345', 'KEK 2184'], array_column($catechism['citations'], 'id'));
        $this->assertSame('A szombat a hat nap művének befejezése: „Isten megáldotta a hetedik napot és megszentelte” (Ter 2,3).', $catechism['citations'][0]['quote']);
        $this->assertSame('Ter 2,3', $catechism['citations'][0]['cites']);
    }

    public function test_key_verse_citations_are_added_when_magister_lists_only_part_of_them(): void
    {
        $passage = FamousPassage::factory()->create();
        $this->fakeMagister([
            'Ter 2' => ['KEK' => $this->answer(40, [
                $this->unit('KEK 100', 'Egy másik versről van szó, amely a teremtést zárja le (Ter 2,1).', 'Ter 2,1'),
            ])],
            'Ter 2,3' => ['KEK' => $this->answer(1, [
                $this->unit('KEK 2000', 'Isten megáldotta a hetedik napot, és ezzel szent időt adott nekünk (Ter 2,3).', 'Ter 2,3'),
            ])],
        ]);

        $this->artisan('szentiras:famous-passage-citations', ['slugs' => ['a-hetedik-nap']])->assertSuccessful();

        $catechism = $passage->refresh()->church_citations[0];
        $this->assertSame(40, $catechism['total']);
        $this->assertSame(['KEK 100', 'KEK 2000'], array_column($catechism['citations'], 'id'));
    }

    public function test_at_most_two_units_of_one_document_are_shown_the_newest_document_first(): void
    {
        $passage = FamousPassage::factory()->create(['key_verse' => null]);
        $units = [
            $this->unit('LS 1', 'Az első mondat a teremtés befejezéséről szól (Ter 2,2).', 'Ter 2,2', 'LS', 'Laudato si’', '2015'),
            $this->unit('LS 2', 'A második mondat a teremtés befejezéséről szól (Ter 2,2).', 'Ter 2,2', 'LS', 'Laudato si’', '2015'),
            $this->unit('LS 3', 'A harmadik mondat a teremtés befejezéséről szól (Ter 2,2).', 'Ter 2,2', 'LS', 'Laudato si’', '2015'),
            $this->unit('DD 5', 'A régebbi levél a vasárnap megszenteléséről szól (Ter 2,3).', 'Ter 2,3', 'DD', 'Dies Domini', '1998'),
        ];
        $this->fakeMagister(['Ter 2' => ['enciklikak' => $this->answer(4, $units)]]);

        $this->artisan('szentiras:famous-passage-citations')->assertSuccessful();

        $encyclicals = $passage->refresh()->church_citations[0];
        $this->assertSame(['LS 1', 'LS 2', 'DD 5'], array_column($encyclicals['citations'], 'id'));
        $this->assertSame('Laudato si’ 1', $encyclicals['citations'][0]['label']);
        $this->assertSame('Ferenc pápa', $encyclicals['citations'][0]['author']);
    }

    public function test_magister_failure_leaves_the_stored_citations_and_fails(): void
    {
        $passage = FamousPassage::factory()->create(['church_citations' => [['key' => 'KEK', 'title' => 'Régi', 'total' => 1, 'citations' => []]]]);
        Http::fake([self::MAGISTER_URL.'/*' => Http::response('', 503)]);

        $this->artisan('szentiras:famous-passage-citations')->assertFailed();

        $this->assertSame('Régi', $passage->refresh()->church_citations[0]['title']);
    }

    public function test_unknown_slug_fails(): void
    {
        Http::fake();

        $this->artisan('szentiras:famous-passage-citations', ['slugs' => ['nincs-ilyen']])
            ->expectsOutput('Nincs ilyen szakasz: nincs-ilyen')
            ->assertFailed();
    }

    public function test_page_quotes_the_documents_and_links_to_the_rest(): void
    {
        config(['services.magister.brand_public' => true]);
        $this->createPassageWithTwelveCitations();

        $this->get('/szakasz/a-hetedik-nap')
            ->assertOk()
            ->assertSee('<h2 class="h4">Az Egyház dokumentumaiban</h2>', false)
            ->assertSee('<blockquote class="mb-1">A szombat a hat nap művének befejezése.</blockquote>', false)
            ->assertSee('href="https://katolikus.hu/dokumentumtar/kek-067-120#K0345"', false)
            ->assertSee('href="'.self::MAGISTER_PUBLIC_URL.'/?q=Ter%202&amp;forras=KEK"', false)
            ->assertSee('Mind a 12 hely a Hittékában');
    }

    /**
     * Until the brand is launched, the source of the citations is neither named nor linked.
     */
    public function test_page_neither_names_nor_links_hitteka_until_the_brand_is_public(): void
    {
        $this->createPassageWithTwelveCitations();

        $this->get('/szakasz/a-hetedik-nap')
            ->assertOk()
            ->assertSee('<blockquote class="mb-1">A szombat a hat nap művének befejezése.</blockquote>', false)
            ->assertSee('href="https://katolikus.hu/dokumentumtar/kek-067-120#K0345"', false)
            ->assertSee('Összesen 12 helyen idézik.')
            ->assertDontSee('Hittéka')
            ->assertDontSee(self::MAGISTER_PUBLIC_URL);
    }

    private function createPassageWithTwelveCitations(): void
    {
        FamousPassage::factory()->create([
            'church_citations' => [[
                'key' => 'KEK',
                'title' => 'A Katolikus Egyház Katekizmusa',
                'total' => 12,
                'citations' => [[
                    'id' => 'KEK 345',
                    'label' => 'KEK 345',
                    'document' => '',
                    'author' => '',
                    'docType' => '',
                    'year' => '',
                    'url' => 'https://katolikus.hu/dokumentumtar/kek-067-120#K0345',
                    'cites' => 'Ter 2,3',
                    'quote' => 'A szombat a hat nap művének befejezése.',
                ]],
            ]],
        ]);
    }

    public function test_page_without_citations_has_no_section_but_loads_the_commentaries(): void
    {
        FamousPassage::factory()->create();

        $this->get('/szakasz/a-hetedik-nap')
            ->assertOk()
            ->assertDontSee('Az Egyház dokumentumaiban')
            ->assertSee('class="commentary-container"', false)
            ->assertSee('data-reference="Ter 2"', false);
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $answers  reference => source group => answer
     */
    private function fakeMagister(array $answers): void
    {
        Http::fake(function (Request $request) use ($answers) {
            $answer = $answers[$request['q']][$request['forras']] ?? ['question' => $request['q'], 'units' => []];

            return Http::response($answer);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $units
     * @return array<string, mixed>
     */
    private function answer(int $total, array $units): array
    {
        return ['idezi' => ['refs' => ['Ter 2'], 'total' => $total], 'units' => $units];
    }

    /**
     * @return array<string, mixed>
     */
    private function unit(string $id, string $text, string $cites, string $source = 'KEK', string $document = 'A Katolikus Egyház Katekizmusa', string $year = ''): array
    {
        return [
            'id' => $id,
            'source' => $source,
            'n' => (int) substr($id, strrpos($id, ' ') + 1),
            'text' => $text,
            'footnotes' => [],
            'document' => $document,
            'author' => $source === 'KEK' ? '' : 'Ferenc pápa',
            'doc_type' => $source === 'KEK' ? '' : 'enciklika',
            'year' => $year,
            'url' => 'https://katolikus.hu/dokumentumtar/'.strtolower($source),
            'cites' => $cites,
        ];
    }
}
