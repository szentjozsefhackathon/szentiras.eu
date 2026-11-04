<?php

namespace SzentirasHu\Test\Search;

use SzentirasHu\Service\Search\ChurchDocumentLinks;
use SzentirasHu\Test\Common\TestCase;

/**
 * The documents citing a meaning-search hit link to where they can be read on katolikus.hu.
 */
class ChurchDocumentLinksTest extends TestCase
{
    private function urlOf(string $unitId): ?string
    {
        return (new ChurchDocumentLinks())->urlOf($unitId);
    }

    public function test_catechism_paragraph_links_to_its_anchor_on_its_page(): void
    {
        $this->assertSame('https://katolikus.hu/dokumentumtar/kek-338-387#K1210', $this->urlOf('KEK 1210'));
        $this->assertSame('https://katolikus.hu/dokumentumtar/kek-299-303#K1075', $this->urlOf('KEK 1075'));
        $this->assertSame('https://katolikus.hu/dokumentumtar/kek-017-022#K0001', $this->urlOf('KEK 1'));
        $this->assertSame('https://katolikus.hu/dokumentumtar/kek-697-723#K2865', $this->urlOf('KEK 2865'));
    }

    public function test_canon_links_to_the_page_that_holds_it(): void
    {
        $this->assertSame(
            'https://torvenykonyv.katolikus.hu/www.vatican.va/archive/cod-iuris-canonici/hu/documents/cic_konyvI_0007-0022_hu.html',
            $this->urlOf('CIC 22'),
        );
    }

    public function test_other_documents_link_to_the_document(): void
    {
        $this->assertSame('https://katolikus.hu/dokumentumtar/amoris-laetitia', $this->urlOf('AL 12'));
        $this->assertStringStartsWith('https://katolikus.hu/dokumentumtar/', $this->urlOf('Komp 210'));
        $this->assertStringStartsWith('https://www.vatican.va/', $this->urlOf('LG 12'));
    }

    public function test_unknown_or_malformed_units_have_no_link(): void
    {
        $this->assertNull($this->urlOf('XYZ 1'));
        $this->assertNull($this->urlOf('KEK 0'));
        $this->assertNull($this->urlOf('KEK'));
        $this->assertNull($this->urlOf(''));
    }

    public function test_no_link_points_to_magister(): void
    {
        $this->assertStringNotContainsString('magister', (string) file_get_contents(resource_path('data/church-document-links.json')));
    }
}
