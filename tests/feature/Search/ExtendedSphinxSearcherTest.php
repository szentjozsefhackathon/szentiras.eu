<?php

namespace SzentirasHu\Test\Search;

use SzentirasHu\Data\Repository\SynonymRepository;
use SzentirasHu\Service\Search\ExtendedSearchHit;
use SzentirasHu\Service\Search\ExtendedSearchParams;
use SzentirasHu\Service\Search\ExtendedSphinxSearcher;
use SzentirasHu\Service\Search\SphinxSearcher;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The extended search matches like the full text search does, and with a quorum it also
 * accepts verses missing some of the words. Sphinx itself is not available in tests, so
 * these pin down the query sent to it and the reading of its rows.
 */
class ExtendedSphinxSearcherTest extends FastDatabaseTestCase
{
    private function searcherFor(string $text, ?float $quorum = null, array $translationIds = [1001]): ExtendedSphinxSearcher
    {
        $params = new ExtendedSearchParams();
        $params->text = $text;
        $params->quorum = $quorum;
        $params->translationIds = $translationIds;

        return new ExtendedSphinxSearcher($params);
    }

    public function test_without_quorum_the_match_is_the_full_text_search_match(): void
    {
        $this->assertSame(
            SphinxSearcher::matchExpressionFor('kezdetben teremtette'),
            $this->searcherFor('kezdetben teremtette')->matchExpression(['kezdetben', 'teremtette'])
        );
    }

    public function test_quorum_adds_a_partial_match_of_the_counted_words_in_the_verse_field(): void
    {
        $expression = $this->searcherFor('nincs emberem aki bevigyen a medencébe', 0.6)
            ->matchExpression(['nincs', 'emberem', 'aki', 'bevigyen', 'medencébe']);

        $this->assertStringStartsWith(SphinxSearcher::matchExpressionFor('nincs emberem aki bevigyen a medencébe'), $expression);
        $this->assertStringEndsWith(' | ( @verse "nincs emberem aki bevigyen medencébe"/0.60 )', $expression);
    }

    public function test_quorum_is_ignored_when_fewer_than_two_words_count(): void
    {
        $this->assertSame(
            SphinxSearcher::matchExpressionFor('a medencébe'),
            $this->searcherFor('a medencébe', 0.6)->matchExpression(['medencébe'])
        );
    }

    public function test_frequent_words_are_not_counted(): void
    {
        $this->assertSame(['nincs', 'emberem', 'medencébe'], ExtendedSphinxSearcher::countedWords([
            ['word' => 'nincs', 'share' => 0.01],
            ['word' => 'emberem', 'share' => 0.0001],
            ['word' => 'a', 'share' => 0.57],
            ['word' => 'medencébe', 'share' => 0.00001],
        ]));
    }

    public function test_a_repeated_word_is_counted_once(): void
    {
        $this->assertSame(['ember', 'fia'], ExtendedSphinxSearcher::countedWords([
            ['word' => 'ember', 'share' => 0.02],
            ['word' => 'fia', 'share' => 0.02],
            ['word' => 'ember', 'share' => 0.02],
        ]));
    }

    public function test_every_word_counts_when_all_of_them_are_frequent(): void
    {
        $this->assertSame(['az', 'és', 'a'], ExtendedSphinxSearcher::countedWords([
            ['word' => 'az', 'share' => 0.4],
            ['word' => 'és', 'share' => 0.48],
            ['word' => 'a', 'share' => 0.57],
        ]));
    }

    public function test_word_factors_are_measured_against_any_of_the_words_in_the_verse_field(): void
    {
        $this->assertSame('@verse "nincs emberem a medencébe"/1', ExtendedSphinxSearcher::anyWordMatch(['nincs', 'emberem', 'a', 'medencébe']));
    }

    public function test_each_word_is_matched_with_its_own_synonyms_only(): void
    {
        app(SynonymRepository::class)->addSynonyms(['szeretet', 'agapé']);
        app(SynonymRepository::class)->addSynonyms(['türelmes', 'béketűrő']);

        $expression = SphinxSearcher::matchExpressionFor('szeretet türelmes');

        $this->assertStringContainsString('(@verse3 ("szeretet" | szeretet | *szeretet* | ', $expression);
        $this->assertMatchesRegularExpression('/\(@verse3 \("szeretet"[^)]*agapé[^)]*\)\)/u', $expression);
        $this->assertMatchesRegularExpression('/\(@verse3 \("türelmes"[^)]*béketűrő[^)]*\)\)/u', $expression);
        $this->assertDoesNotMatchRegularExpression('/\(@verse3 \("türelmes"[^)]*agapé/u', $expression);
    }

    public function test_of_equal_translations_of_a_verse_the_first_searched_is_preferred(): void
    {
        $this->assertSame(
            'IF(trans=3,0,IF(trans=1,1,IF(trans=4,2,3)))',
            $this->searcherFor('kezdetben', translationIds: [3, 1, 4])->translationPreference()
        );
    }

    public function test_text_without_words_finds_nothing_without_asking_sphinx(): void
    {
        $result = $this->searcherFor(' ?! ')->get();

        $this->assertSame([], $result->hits);
        $this->assertSame(0, $result->totalHitCount);
    }

    public function test_no_translation_to_search_finds_nothing_without_asking_sphinx(): void
    {
        $result = $this->searcherFor('kezdetben', translationIds: [])->get();

        $this->assertSame(0, $result->totalHitCount);
    }

    public function test_only_verse_text_types_are_searched_not_headings(): void
    {
        $verseTextTypes = ExtendedSphinxSearcher::verseTextTypes();

        $this->assertContains(901, $verseTextTypes);
        $this->assertContains(902, $verseTextTypes);
        $this->assertNotContains(701, $verseTextTypes);
        $this->assertNotContains(3000, $verseTextTypes);
    }

    public function test_hit_carries_its_word_factors(): void
    {
        $hit = ExtendedSearchHit::fromSphinxRow(
            ['gepi' => 'JHN_5_7', 'trans' => 4, 'usx_code' => 'JHN', 'chapter' => 5, 'weight()' => '865959.8'],
            ['lcs' => 3, 'word_count' => 4, 'exact_hit' => 0],
        );

        $this->assertSame(3, $hit->longestRun);
        $this->assertSame(4, $hit->wordCount);
        $this->assertFalse($hit->exactHit);
    }

    public function test_hit_reads_the_verse_number_from_the_gepi(): void
    {
        $hit = ExtendedSearchHit::fromSphinxRow([
            'id' => 365888,
            'gepi' => '1CO_13_4',
            'trans' => 3,
            'usx_code' => '1CO',
            'chapter' => 13,
            'weight()' => '4098130.8',
        ]);

        $this->assertSame('1CO', $hit->usxCode);
        $this->assertSame(13, $hit->chapter);
        $this->assertSame(4, $hit->verse);
        $this->assertSame(3, $hit->translationId);
        $this->assertSame(4098130.8, $hit->weight);
    }
}
