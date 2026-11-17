<?php

namespace SzentirasHu\Test;

use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Models\BookIntroduction;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The seeded TESTTRANS book "Ter." is GEN, so its popular names all differ from the official name.
 */
class BookPageSeoTest extends FastDatabaseTestCase
{
    public function test_title_leads_with_the_popular_name_and_keeps_the_official_one(): void
    {
        $this->get('/TESTTRANS/Ter')
            ->assertOk()
            ->assertSee('<title>Teremtés könyve – teljes szöveg | Ter. (TESTTRANS)</title>', false);
    }

    public function test_title_leads_with_the_official_name_when_it_is_the_popular_one(): void
    {
        Book::query()->whereKey(99101)->update(['name' => 'A Teremtés könyve']);

        $this->get('/TESTTRANS/Ter')
            ->assertOk()
            ->assertSee('<title>A Teremtés könyve – teljes szöveg | Translation Name 1</title>', false)
            ->assertSee('Más néven: Mózes első könyve, Genezis', false);
    }

    public function test_protestant_translation_title_keeps_its_own_book_name(): void
    {
        Translation::query()->whereKey(1001)->update(['denom' => 'protestáns']);
        Book::query()->whereKey(99101)->update(['name' => 'Mózes első könyve']);

        $this->get('/TESTTRANS/Ter')
            ->assertOk()
            ->assertSee('<title>Mózes első könyve – teljes szöveg | Translation Name 1</title>', false)
            ->assertSee('Más néven: Teremtés könyve, Genezis', false);
    }

    public function test_title_mentions_the_introduction_when_there_is_one(): void
    {
        BookIntroduction::factory()->create();

        $this->get('/TESTTRANS/Ter')
            ->assertOk()
            ->assertSee('<title>Teremtés könyve – bevezetés és teljes szöveg | Ter. (TESTTRANS)</title>', false);
    }

    public function test_official_name_stays_the_heading_and_popular_names_are_listed(): void
    {
        $this->get('/TESTTRANS/Ter')
            ->assertOk()
            ->assertSeeInOrder([
                '<h1 class="h4">Ter.</h1>',
                'Más néven: Teremtés könyve, Mózes első könyve, Genezis',
            ], false);
    }

    public function test_description_starts_with_the_names_and_the_book_introduction(): void
    {
        BookIntroduction::factory()->create([
            'paragraphs' => [
                ['style' => 'heading', 'segments' => [['text' => 'Címsor']]],
                ['style' => 'paragraph', 'segments' => [['text' => 'A könyv a világ teremtésével kezdődik.']]],
            ],
        ]);

        $this->get('/TESTTRANS/Ter')
            ->assertOk()
            ->assertSee('<meta name="description" content="Teremtés könyve (Ter.) – Translation Name 1. A könyv a világ teremtésével kezdődik.">', false);
    }

    public function test_structured_data_names_the_book_with_its_popular_names(): void
    {
        $response = $this->get('/TESTTRANS/Ter');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="'.url('/TESTTRANS/Ter').'" />', false);
        $response->assertSee('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Book","name":"Ter.","alternateName":["Teremtés könyve","Mózes első könyve","Genezis"],"inLanguage":"hu","url":"'.url('/TESTTRANS/Ter').'","isPartOf":{"@type":"Book","name":"Translation Name 1"}}</script>', false);
    }
}
