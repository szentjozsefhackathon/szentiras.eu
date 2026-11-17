<?php

namespace SzentirasHu\Service\BookIntroduction;

use RuntimeException;
use SimpleXMLElement;

/**
 * Reads the book and book-group introductions (<Bevezeto> elements) from a
 * BibliaForditas XML source (SZIT, KNB, BD, ...).
 *
 * An introduction inside a <Book> belongs to that book. One inside a
 * <SubCorpus> belongs to a group of books: its `hiv` attribute names the range
 * (e.g. "GEN-DEU", "JHN.1-3JN_a"), but some sources label group introductions
 * with a single book code, so a range that does not resolve to several books
 * falls back to every book of the SubCorpus. When the same range heads several
 * SubCorpora (SZIT splits "1CH-NEH" in two), each introduction covers only the
 * books of its own SubCorpus. An untitled group introduction is titled with the
 * name of its SubCorpus.
 */
class BookIntroductionXmlParser
{
    public const STYLE_PARAGRAPH = 'paragraph';

    public const STYLE_HEADING = 'heading';

    public const STYLE_SUBHEADING = 'subheading';

    public const STYLE_NOTE = 'note';

    private const TITLE_STYLE = 'Bevezető főcím';

    /**
     * @var array<string, string>
     */
    private const STYLE_MAP = [
        'Bevezető főcím' => self::STYLE_HEADING,
        'Bevezető címsor1' => self::STYLE_HEADING,
        'Bevezető alcím' => self::STYLE_HEADING,
        'Bevezető címsor2' => self::STYLE_SUBHEADING,
        'Bevezető lábjegyzet' => self::STYLE_NOTE,
    ];

    /**
     * Characters closing a cross-reference text that belong to the sentence, not to the reference.
     */
    private const REFERENCE_TRAILING_PUNCTUATION = ').,;:';

    /**
     * @return list<array{usx_codes: list<string>, is_group: bool, title: string|null, paragraphs: list<array{style: string, segments: list<array{text: string, italic?: bool, reference?: string}>}>, position: int}>
     */
    public function parse(string $xmlContent): array
    {
        $previousErrorSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        libxml_use_internal_errors($previousErrorSetting);
        if ($xml === false) {
            throw new RuntimeException('Az XML nem olvasható be.');
        }

        $bookOrder = $this->usxCodesOf($xml->xpath('//Book') ?: []);
        $groupRangeCounts = array_count_values(array_map(
            fn (SimpleXMLElement $introductionElement): string => (string) $introductionElement['hiv'],
            $xml->xpath('//SubCorpus/Bevezeto') ?: [],
        ));

        $introductions = [];
        foreach ($xml->xpath('//Bevezeto') ?: [] as $position => $introductionElement) {
            $parent = $introductionElement->xpath('..')[0];
            $isGroup = $parent->getName() !== 'Book';
            $usxCodes = $isGroup
                ? $this->groupUsxCodes((string) $introductionElement['hiv'], $parent, $bookOrder, $groupRangeCounts)
                : [(string) $parent['usxrov']];

            [$title, $paragraphs] = $this->parseParagraphs($introductionElement);
            if ($paragraphs === [] || $usxCodes === []) {
                continue;
            }
            if ($title === null && $isGroup) {
                $title = trim((string) $parent['Megnev']) ?: null;
            }

            $introductions[] = [
                'usx_codes' => $usxCodes,
                'is_group' => $isGroup,
                'title' => $title,
                'paragraphs' => $paragraphs,
                'position' => $position + 1,
            ];
        }

        return $introductions;
    }

    /**
     * Resolves a `hiv` range such as "GEN-DEU", "JHN.1-3JN_a" or "MAT bev" to
     * USX codes in print order, or null if it names unknown books.
     *
     * @param  list<string>  $bookOrder
     * @return list<string>|null
     */
    public function resolveRange(string $hiv, array $bookOrder): ?array
    {
        $range = preg_replace('/[\s_].*$/u', '', trim($hiv));
        if ($range === '') {
            return null;
        }

        $usxCodes = [];
        foreach (explode('.', $range) as $part) {
            if (preg_match('/^(\d)-(\d)([A-Z]+)$/', $part, $matches) === 1) {
                [$first, $last] = ["{$matches[1]}{$matches[3]}", "{$matches[2]}{$matches[3]}"];
            } elseif (str_contains($part, '-')) {
                [$first, $last] = explode('-', $part, 2);
            } else {
                [$first, $last] = [$part, $part];
            }

            $firstIndex = array_search($first, $bookOrder, true);
            $lastIndex = array_search($last, $bookOrder, true);
            if ($firstIndex === false || $lastIndex === false || $lastIndex < $firstIndex) {
                return null;
            }
            array_push($usxCodes, ...array_slice($bookOrder, $firstIndex, $lastIndex - $firstIndex + 1));
        }

        return array_values(array_unique($usxCodes));
    }

    /**
     * @param  list<string>  $bookOrder
     * @param  array<string, int>  $groupRangeCounts  How many group introductions carry each `hiv`.
     * @return list<string>
     */
    private function groupUsxCodes(string $hiv, SimpleXMLElement $parent, array $bookOrder, array $groupRangeCounts): array
    {
        $subCorpusUsxCodes = $this->usxCodesOf($parent->xpath('.//Book') ?: []);
        $range = $this->resolveRange($hiv, $bookOrder);
        if ($range === null || count($range) < 2) {
            return $subCorpusUsxCodes;
        }

        if (($groupRangeCounts[$hiv] ?? 0) > 1) {
            $ownPartOfRange = array_values(array_intersect($range, $subCorpusUsxCodes));
            if ($ownPartOfRange !== []) {
                return $ownPartOfRange;
            }
        }

        return $range;
    }

    /**
     * @param  array<SimpleXMLElement>  $bookElements
     * @return list<string>
     */
    private function usxCodesOf(array $bookElements): array
    {
        return array_values(array_filter(array_map(
            fn (SimpleXMLElement $book): string => (string) $book['usxrov'],
            $bookElements,
        )));
    }

    /**
     * @return array{0: string|null, 1: list<array{style: string, segments: list<array{text: string, italic?: bool, reference?: string}>}>}
     */
    private function parseParagraphs(SimpleXMLElement $introductionElement): array
    {
        $title = null;
        $paragraphs = [];
        foreach ($introductionElement->Bek as $paragraphElement) {
            $segments = $this->parseSegments($paragraphElement);
            $text = trim(implode('', array_column($segments, 'text')));
            if ($text === '') {
                continue;
            }

            $sourceStyle = (string) $paragraphElement['Style'];
            if ($sourceStyle === self::TITLE_STYLE && $title === null && $paragraphs === []) {
                $title = $text;

                continue;
            }

            $paragraphs[] = [
                'style' => $this->styleOf($sourceStyle, $text),
                'segments' => $segments,
            ];
        }

        return [$title, $paragraphs];
    }

    /**
     * Short all-capital paragraphs are section headings typed as body text in the source.
     */
    private function styleOf(string $sourceStyle, string $text): string
    {
        if (isset(self::STYLE_MAP[$sourceStyle])) {
            return self::STYLE_MAP[$sourceStyle];
        }

        $isShortAllCapitals = mb_strlen($text) < 100
            && preg_match('/\p{Lu}/u', $text) === 1
            && mb_strtoupper($text) === $text;

        return $isShortAllCapitals ? self::STYLE_HEADING : self::STYLE_PARAGRAPH;
    }

    /**
     * Text runs (<Sz>, italic when St="i") and cross-references (<K>) in order.
     * The source uses "|" as a tab stop in outlines ("Ter 1-11:|őstörténet").
     *
     * @return list<array{text: string, italic?: bool, reference?: string}>
     */
    private function parseSegments(SimpleXMLElement $paragraphElement): array
    {
        $segments = [];
        foreach ($paragraphElement->children() as $child) {
            if ($child->getName() === 'Sz') {
                $text = str_replace('|', "\t", (string) $child->T);
                if ($text === '') {
                    continue;
                }
                $segment = ['text' => $text];
                if ((string) $child['St'] === 'i') {
                    $segment['italic'] = true;
                }
                $segments[] = $segment;
            } elseif ($child->getName() === 'K') {
                array_push($segments, ...$this->referenceSegments((string) $child['F']));
            }
        }

        return $segments;
    }

    /**
     * @return list<array{text: string, reference?: string}>
     */
    private function referenceSegments(string $displayText): array
    {
        $reference = rtrim($displayText, self::REFERENCE_TRAILING_PUNCTUATION);
        $trailing = substr($displayText, strlen($reference));
        if ($reference === '') {
            return $trailing === '' ? [] : [['text' => $trailing]];
        }

        $segments = [['text' => $reference, 'reference' => $reference]];
        if ($trailing !== '') {
            $segments[] = ['text' => $trailing];
        }

        return $segments;
    }
}
