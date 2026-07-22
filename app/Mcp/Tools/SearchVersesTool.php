<?php

namespace SzentirasHu\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use SzentirasHu\Mcp\SearchResultFormatter;
use SzentirasHu\Mcp\TranslationResolver;
use SzentirasHu\Mcp\UnknownTranslationException;
use SzentirasHu\Service\Search\BookFilter;
use SzentirasHu\Service\Search\FullTextSearchParams;
use SzentirasHu\Service\Search\SearchService;
use SzentirasHu\Service\Search\UnknownBookException;

class SearchVersesTool extends Tool
{
    /**
     * Kept low on purpose: a search answer is paid for in tokens, and an agent that needs
     * more should narrow the search instead of reading more verses.
     */
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    protected string $name = 'search-verses';

    protected string $title = 'Search the Bible text';

    protected string $description = 'Finds Bible verses containing the given Hungarian words, searching the actual text of a translation, with synonyms and word forms taken into account. Use it to locate a passage whose wording is remembered but not its reference, or to see where a topic occurs. Returns only the reference and the text of each hit, so keep the limit small and narrow the search by book instead of asking for many verses.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()
                ->description('The Hungarian words to search for, e.g. "szeretet türelmes".')
                ->required(),
            'book' => $schema->string()
                ->description('Restrict the search to "old_testament", "new_testament", a single book given by its Hungarian abbreviation (e.g. "Jn") or USX code (e.g. "JHN"). Defaults to the whole Bible.'),
            'translation' => $schema->string()
                ->description('Optional translation abbreviation (e.g. RUF, SZIT, KNB). Defaults to the translation this endpoint is configured for. Only pass this to deliberately override the user\'s own tradition.'),
            'limit' => $schema->integer()
                ->description('Maximum number of verses to return, 1-'.self::MAX_LIMIT.'. Defaults to '.self::DEFAULT_LIMIT.'.'),
        ];
    }

    public function handle(Request $request, TranslationResolver $resolver, SearchService $searchService): Response
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'book' => ['nullable', 'string'],
            'translation' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        try {
            $translation = $resolver->resolve($validated['translation'] ?? null);
            $usxCodes = BookFilter::usxCodesFor($validated['book'] ?? null);
        } catch (UnknownTranslationException|UnknownBookException $exception) {
            return Response::error($exception->getMessage());
        }

        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;

        $searchParams = new FullTextSearchParams();
        $searchParams->text = $validated['text'];
        $searchParams->translationId = $translation->id;
        $searchParams->usxCodes = $usxCodes;
        $searchParams->grouping = 'verse';
        $searchParams->synonyms = true;
        $searchParams->limit = $limit;

        $verses = SearchResultFormatter::verses($searchService->getDetailedResults($searchParams));

        if ($verses === []) {
            return Response::json([
                'query' => $this->describeQuery($validated, $translation->abbrev, $limit),
                'totalHitCount' => 0,
                'verses' => [],
            ]);
        }

        $totalHitCount = $this->countHits($searchService, $searchParams);

        return Response::json([
            'query' => $this->describeQuery($validated, $translation->abbrev, $limit),
            'totalHitCount' => $totalHitCount,
            'truncated' => $totalHitCount > count($verses),
            'verses' => $verses,
        ]);
    }

    /**
     * The number of verses matching the search regardless of the limit, so that the agent
     * can tell whether it is looking at all of them.
     */
    private function countHits(SearchService $searchService, FullTextSearchParams $searchParams): int
    {
        $countParams = clone $searchParams;
        $countParams->countOnly = true;

        return (int) ($searchService->getSimpleResults($countParams)->hitCount ?? 0);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function describeQuery(array $validated, string $translationAbbrev, int $limit): array
    {
        return [
            'text' => $validated['text'],
            'translation' => $translationAbbrev,
            'book' => $validated['book'] ?? BookFilter::ALL,
            'limit' => $limit,
        ];
    }
}
