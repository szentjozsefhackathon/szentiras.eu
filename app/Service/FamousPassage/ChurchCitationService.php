<?php

namespace SzentirasHu\Service\FamousPassage;

use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\Magister\MagisterClient;
use SzentirasHu\Service\Magister\MagisterUnavailableException;
use SzentirasHu\Service\Search\ChurchDocumentLinks;

/**
 * Where the Church's documents cite a famous passage, quoted in their own words: the Catechism,
 * the Second Vatican Council, encyclicals and apostolic documents, as Magister finds them.
 * Collected once and stored with the passage, so the page does not depend on Magister.
 *
 * @phpstan-type ChurchCitation array{id: string, label: string, document: string, author: string, docType: string, year: string, url: string|null, cites: string, quote: string}
 * @phpstan-type ChurchCitationGroup array{key: string, title: string, total: int, citations: list<ChurchCitation>}
 */
class ChurchCitationService
{
    /**
     * The source groups shown, in order, with how many citations each shows at most. Pastoral
     * messages, homilies and bishops' conference documents are left out: they are not teaching.
     */
    public const GROUPS = [
        'KEK' => ['title' => 'A Katolikus Egyház Katekizmusa', 'shown' => 8],
        'zsinat' => ['title' => 'A II. Vatikáni Zsinat dokumentumai', 'shown' => 5],
        'enciklikak' => ['title' => 'Pápai enciklikák', 'shown' => 5],
        'apostoli' => ['title' => 'Apostoli buzdítások és levelek', 'shown' => 5],
    ];

    private const SHOWN_PER_DOCUMENT = 2;

    public function __construct(
        protected MagisterClient $magisterClient,
        protected CitationQuoteExtractor $quoteExtractor,
        protected ChurchDocumentLinks $churchDocumentLinks,
    ) {}

    /**
     * @return list<ChurchCitationGroup>
     *
     * @throws MagisterUnavailableException
     */
    public function collect(FamousPassage $passage): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => $group) {
            $citing = $this->magisterClient->unitsCiting($passage->reference, $key);
            $units = $citing['units'];
            if ($passage->key_verse !== null && $citing['total'] > count($units)) {
                $units = [...$this->magisterClient->unitsCiting($passage->key_verse, $key)['units'], ...$units];
            }

            $citations = $this->select($units, $group['shown']);
            if ($citations !== []) {
                $groups[] = [
                    'key' => $key,
                    'title' => $group['title'],
                    'total' => max($citing['total'], count($citations)),
                    'citations' => $citations,
                ];
            }
        }

        return $groups;
    }

    /**
     * The first quotable units, at most two from one document (the Catechism counts each paragraph
     * as its own), ordered the way they are read:
     * the Catechism by paragraph, the other documents from the newest.
     *
     * @param  list<array<string, mixed>>  $units  the ones citing the key verse first
     * @return list<ChurchCitation>
     */
    private function select(array $units, int $shown): array
    {
        $selected = [];
        $perDocument = [];
        foreach ($units as $unit) {
            $id = (string) $unit['id'];
            $document = str_starts_with($id, 'KEK ') ? $id : (string) ($unit['source'] ?? $id);
            if (isset($selected[$id]) || ($perDocument[$document] ?? 0) >= self::SHOWN_PER_DOCUMENT) {
                continue;
            }
            $quote = $this->quoteExtractor->extract((string) ($unit['text'] ?? ''), $unit['footnotes'] ?? [], (string) $unit['cites']);
            if ($quote === null) {
                continue;
            }

            $selected[$id] = $this->citation($unit, $quote);
            $perDocument[$document] = ($perDocument[$document] ?? 0) + 1;
            if (count($selected) === $shown) {
                break;
            }
        }

        $citations = array_values($selected);
        usort($citations, fn (array $a, array $b): int => str_starts_with($a['id'], 'KEK ')
            ? $this->unitNumber($a['id']) <=> $this->unitNumber($b['id'])
            : [$b['year'], $this->unitNumber($a['id'])] <=> [$a['year'], $this->unitNumber($b['id'])]);

        return $citations;
    }

    /**
     * @param  array<string, mixed>  $unit
     * @return ChurchCitation
     */
    private function citation(array $unit, string $quote): array
    {
        $id = (string) $unit['id'];
        $document = (string) ($unit['document'] ?? '');
        $isCatechism = str_starts_with($id, 'KEK ');

        return [
            'id' => $id,
            'label' => $isCatechism || $document === '' ? $id : "{$document} {$this->unitNumber($id)}",
            'document' => $document,
            'author' => (string) ($unit['author'] ?? ''),
            'docType' => (string) ($unit['doc_type'] ?? ''),
            'year' => (string) ($unit['year'] ?? ''),
            'url' => $this->churchDocumentLinks->urlOf($id) ?? (is_string($unit['url'] ?? null) ? $unit['url'] : null),
            'cites' => (string) $unit['cites'],
            'quote' => $quote,
        ];
    }

    private function unitNumber(string $id): int
    {
        return (int) substr($id, (int) strrpos($id, ' ') + 1);
    }
}
