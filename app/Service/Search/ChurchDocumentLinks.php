<?php

namespace SzentirasHu\Service\Search;

/**
 * Where a cited unit of a Church document ("KEK 388", "LG 12", "CIC 1251") can be read: the
 * document on katolikus.hu (or the Hungarian vatican.va page of the Vatican II documents).
 * Catechism paragraphs link to their own anchor, canons to the page that holds them.
 *
 * The table, resources/data/church-document-links.json, is generated from the document store
 * of the meaning search and the katolikus.hu Catechism and Code of Canon Law pages.
 */
class ChurchDocumentLinks
{
    private const DATA_FILE = 'data/church-document-links.json';

    /** @var array{documents: array<string, string>, paragraphPages: array<string, array<int, array{0: int, 1: string}>>}|null */
    private ?array $links = null;

    public function urlOf(string $unitId): ?string
    {
        if (! preg_match('/^(\S+)\s+(\d+)$/u', trim($unitId), $matches)) {
            return null;
        }
        [, $source, $number] = $matches;
        $number = (int) $number;
        $links = $this->links();

        if (isset($links['paragraphPages'][$source])) {
            $page = $this->pageOf($links['paragraphPages'][$source], $number);
            if ($page === null) {
                return null;
            }

            return $source === 'KEK' ? sprintf('%s#K%04d', $page, $number) : $page;
        }

        return $links['documents'][$source] ?? null;
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $pages  first unit number of each page, ascending
     */
    private function pageOf(array $pages, int $number): ?string
    {
        $found = null;
        foreach ($pages as [$firstNumber, $url]) {
            if ($firstNumber > $number) {
                break;
            }
            $found = $url;
        }

        return $found;
    }

    /**
     * @return array{documents: array<string, string>, paragraphPages: array<string, array<int, array{0: int, 1: string}>>}
     */
    private function links(): array
    {
        return $this->links ??= json_decode(file_get_contents(resource_path(self::DATA_FILE)), true, flags: JSON_THROW_ON_ERROR);
    }
}
