<?php

namespace SzentirasHu\Test;

use RuntimeException;
use SzentirasHu\Service\BookIntroduction\BookIntroductionXmlParser;
use SzentirasHu\Test\Common\TestCase;

class BookIntroductionXmlParserTest extends TestCase
{
    private BookIntroductionXmlParser $parser;

    /**
     * @var list<array<string, mixed>>
     */
    private array $introductions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BookIntroductionXmlParser;
        $this->introductions = $this->parser->parse(file_get_contents(__DIR__.'/../fixtures/book-introductions.xml'));
    }

    public function test_it_reads_every_introduction_in_print_order(): void
    {
        $this->assertCount(5, $this->introductions);
        $this->assertSame([1, 2, 3, 4, 5], array_column($this->introductions, 'position'));
    }

    public function test_a_group_introduction_covers_the_books_of_its_range(): void
    {
        $groupIntroduction = $this->introductions[0];

        $this->assertTrue($groupIntroduction['is_group']);
        $this->assertSame(['GEN', 'EXO', 'LEV', 'NUM'], $groupIntroduction['usx_codes']);
        $this->assertSame('Bevezetés a Törvény könyveihez', $groupIntroduction['title']);
    }

    public function test_a_group_introduction_labelled_with_a_single_book_covers_its_sub_corpus(): void
    {
        $catholicLettersIntroduction = $this->introductions[4];

        $this->assertTrue($catholicLettersIntroduction['is_group']);
        $this->assertSame(['1JN', '2JN'], $catholicLettersIntroduction['usx_codes']);
    }

    public function test_an_untitled_group_introduction_is_titled_with_its_sub_corpus_name(): void
    {
        $this->assertSame('A katolikus levelek', $this->introductions[4]['title']);
    }

    public function test_a_range_repeated_over_several_sub_corpora_is_split_between_them(): void
    {
        $this->assertSame(['1CH', '2CH'], $this->introductions[2]['usx_codes']);
        $this->assertSame(['EZR', 'NEH'], $this->introductions[3]['usx_codes']);
    }

    public function test_a_book_introduction_belongs_to_its_book_only(): void
    {
        $bookIntroduction = $this->introductions[1];

        $this->assertFalse($bookIntroduction['is_group']);
        $this->assertSame(['GEN'], $bookIntroduction['usx_codes']);
    }

    public function test_paragraph_styles_are_mapped_and_empty_paragraphs_are_skipped(): void
    {
        $this->assertSame(['heading', 'paragraph'], array_column($this->introductions[0]['paragraphs'], 'style'));
        $this->assertSame(['paragraph', 'heading'], array_column($this->introductions[1]['paragraphs'], 'style'));
        $this->assertSame(['paragraph', 'note'], array_column($this->introductions[4]['paragraphs'], 'style'));
    }

    public function test_italics_and_cross_references_are_kept_as_segments(): void
    {
        $this->assertSame([
            ['text' => 'A teremtés '],
            ['text' => 'kezdetén', 'italic' => true],
            ['text' => ' Isten szól (vö. '],
            ['text' => 'Kiv 20', 'reference' => 'Kiv 20'],
            ['text' => ')'],
            ['text' => '.'],
        ], $this->introductions[1]['paragraphs'][0]['segments']);
    }

    public function test_the_pipe_tab_stop_becomes_a_tab(): void
    {
        $this->assertSame("Ter 1-11:\tőstörténet", $this->introductions[0]['paragraphs'][1]['segments'][0]['text']);
    }

    public function test_ranges_expand_numbered_book_shorthand_and_ignore_suffixes(): void
    {
        $bookOrder = ['MAT', 'JHN', 'ACT', '1JN', '2JN', '3JN', 'JUD', 'REV'];

        $this->assertSame(['JHN', '1JN', '2JN', '3JN'], $this->parser->resolveRange('JHN.1-3JN_a', $bookOrder));
        $this->assertSame(['MAT'], $this->parser->resolveRange('MAT bev', $bookOrder));
        $this->assertNull($this->parser->resolveRange('GEN-DEU', $bookOrder));
    }

    public function test_unreadable_xml_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser->parse('<BibliaForditas><nem zárt>');
    }
}
