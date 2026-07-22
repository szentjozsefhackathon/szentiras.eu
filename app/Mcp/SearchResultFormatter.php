<?php

namespace SzentirasHu\Mcp;

/**
 * Flattens a search result into the shortest form that still answers the question.
 *
 * The search services return everything the search page needs to render: the same hit
 * grouped several ways, whole entities, and the word by word analysis of the Greek text.
 * An MCP client pays for all of that in tokens, so a tool answer carries only the reference
 * and the text of each verse.
 */
class SearchResultFormatter
{
    /**
     * @param  array{results?: array<mixed>}|null  $results  A result of
     *                                                       {@see \SzentirasHu\Service\Search\SearchService::getDetailedResults()}
     *                                                       or {@see \SzentirasHu\Service\Search\GreekSearchService::search()}.
     * @return array<int, array{reference: string, text: string, greekText?: string}>
     */
    public static function verses(?array $results, bool $withGreekText = false): array
    {
        $verses = [];

        foreach ($results['results'] ?? [] as $bookResult) {
            $book = $bookResult['book'];

            foreach ($bookResult['verses'] ?? [] as $verse) {
                $formatted = [
                    'reference' => "{$book->abbrev} {$verse['chapter']},{$verse['numv']}",
                    'text' => self::tidy($verse['text'] ?? ''),
                ];

                if ($withGreekText) {
                    $formatted['greekText'] = self::tidy($verse['greekText'] ?? '');
                }

                $verses[] = $formatted;
            }
        }

        return $verses;
    }

    /**
     * The search results keep the markup removal's leftover spacing, which is only noise here.
     */
    private static function tidy(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
