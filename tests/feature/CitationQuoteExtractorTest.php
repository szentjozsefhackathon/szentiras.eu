<?php

namespace SzentirasHu\Test;

use SzentirasHu\Service\FamousPassage\CitationQuoteExtractor;
use SzentirasHu\Test\Common\TestCase;

/**
 * The fixture is Magister's answer to "1Kor 13,1-13" in the Catechism: the units citing the passage,
 * with their text and footnotes as the source has them.
 */
class CitationQuoteExtractorTest extends TestCase
{
    private CitationQuoteExtractor $extractor;

    /**
     * @var array<string, array{text: string, footnotes: array<int, array{n: int, text: string}>, cites: string}>
     */
    private array $units;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new CitationQuoteExtractor;
        $response = json_decode(file_get_contents(__DIR__.'/../fixtures/magister-1kor13-kek.json'), true);
        foreach ($response['units'] as $unit) {
            $this->units[$unit['id']] = $unit;
        }
    }

    public function test_the_sentence_holding_the_citation_is_quoted(): void
    {
        $this->assertSame(
            'Akkor majd „színről színre” fogjuk látni Istent (1Kor 13,12), „úgy, amint van” (1Jn 3,2).',
            $this->extractFromUnit('KEK 163'),
        );
    }

    public function test_a_quotation_is_kept_whole_with_the_sentence_introducing_it(): void
    {
        $this->assertSame(
            'Szent Pál apostol felülmúlhatatlan leírást adott a szeretetről: „A szeretet türelmes, a szeretet jóságos, '
            .'a szeretet nem féltékeny, nem kérkedik, nem is kevély. Nem tapintatlan, nem keresi a maga javát, '
            .'nem gerjed haragra, a rosszat nem rója fel. Nem örül a gonoszságnak, örömét az igazság győzelmében leli. '
            .'Mindent eltűr, mindent elhisz, mindent remél, mindent elvisel.” (1Kor 13,4–7)',
            $this->extractFromUnit('KEK 1825'),
        );
    }

    public function test_a_quotation_opening_its_sentence_does_not_take_the_previous_one(): void
    {
        $this->assertSame('„A szeretet nem keresi a magáét” (1Kor 13,5).', $this->extractFromUnit('KEK 953'));
    }

    public function test_a_citation_in_a_footnote_quotes_the_sentence_carrying_the_marker(): void
    {
        $this->assertSame('Három isteni erény van: a hit, a remény és a szeretet.', $this->extractFromUnit('KEK 1841'));
    }

    public function test_cross_references_and_footnote_markers_are_left_out(): void
    {
        $quote = $this->extractFromUnit('KEK 314');

        $this->assertStringStartsWith('Csak a végén, amikor véget ér töredékes megismerésünk', (string) $quote);
        $this->assertStringNotContainsString('[390]', (string) $quote);
        $this->assertStringNotContainsString('>>', (string) $quote);
        $this->assertStringContainsString('Isten – még a rossz', (string) $quote);
    }

    public function test_a_long_sentence_is_shortened_at_a_word(): void
    {
        $longText = 'Bevezető mondat. '.str_repeat('nagyon hosszú mellékmondat, ', 40).'amely „soha meg nem szűnik” (1Kor 13,8), és így tovább.';

        $quote = (string) $this->extractor->extract($longText, [], '1Kor 13,8');

        $this->assertStringStartsWith('(…) ', $quote);
        $this->assertStringEndsWith('amely „soha meg nem szűnik” (1Kor 13,8), és így tovább.', $quote);
        $this->assertLessThanOrEqual(710, mb_strlen($quote));
    }

    public function test_abbreviations_and_ordinals_do_not_end_a_sentence(): void
    {
        $text = 'Ezt II. János Pál is tanította. Jézus irgalmas atyaként mutatja be az Istent (vö. Lk 15,11–24), aki elénk jön és talpra állít.';

        $this->assertSame(
            'Jézus irgalmas atyaként mutatja be az Istent (vö. Lk 15,11–24), aki elénk jön és talpra állít.',
            $this->extractor->extract($text, [], 'Lk 15,11-24'),
        );
    }

    public function test_a_citation_closing_a_quotation_without_a_full_stop_ends_its_sentence(): void
    {
        $text = 'Az Úr Jézus minden tanítványának meghirdette: "Legyetek tökéletesek, miként a ti mennyei Atyátok tökéletes!" (Mt 5,48)[122] Mindegyikükre elküldte a Szentlelket, hogy egymást úgy szeressék, ahogyan Krisztus szerette őket (vö. Jn 13,34; 15,12).';

        $this->assertSame(
            'Mindegyikükre elküldte a Szentlelket, hogy egymást úgy szeressék, ahogyan Krisztus szerette őket (vö. Jn 13,34; 15,12).',
            $this->extractor->extract($text, [], 'Jn 13,34;15,12'),
        );
    }

    public function test_a_citation_after_a_closed_sentence_belongs_to_that_sentence(): void
    {
        $text = 'Az evangélium boldognak mondja a békeszerzőket, "mert Isten fiainak hívják majd őket". (Mt 5,9) A Zsinat tehát fölszólítja a keresztényeket.';

        $this->assertSame(
            'Az evangélium boldognak mondja a békeszerzőket, "mert Isten fiainak hívják majd őket". (Mt 5,9)',
            $this->extractor->extract($text, [], 'Mt 5,9'),
        );
    }

    public function test_bare_references_and_citations_opening_a_paragraph_are_not_quoted(): void
    {
        $this->assertNull($this->extractor->extract('Az imádság. (Mt 6,9–13)', [], 'Mt 6,9-13'));
        $this->assertNull($this->extractor->extract("Előző bekezdés.\n\n(Jn 1,1.14) A kinyilatkoztatás újdonsága az, hogy Isten dialógusban ismerteti meg magát.", [], 'Jn 1,1.14'));
    }

    public function test_a_unit_not_mentioning_the_reference_gives_nothing(): void
    {
        $this->assertNull($this->extractor->extract('Egy mondat, amely semmit sem idéz, és nincs lábjegyzete sem.', [], '1Kor 13,4'));
        $this->assertNull($this->extractor->extract('Egy mondat a szeretetről (1Kor 13,45) hosszan és részletesen kifejtve.', [], '1Kor 13,4'));
    }

    private function extractFromUnit(string $id): ?string
    {
        $unit = $this->units[$id];

        return $this->extractor->extract($unit['text'], $unit['footnotes'], $unit['cites']);
    }
}
