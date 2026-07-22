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
use SzentirasHu\Service\Search\GreekSearchMode;
use SzentirasHu\Service\Search\GreekSearchParams;
use SzentirasHu\Service\Search\GreekSearchRule;
use SzentirasHu\Service\Search\GreekSearchService;
use SzentirasHu\Service\Search\UnknownBookException;

class SearchGreekTool extends Tool
{
    /**
     * Every Greek hit is answered with its Greek text and its Hungarian translation, so a
     * generous limit costs a lot of tokens.
     */
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    protected string $name = 'search-greek';

    protected string $title = 'Search the Greek New Testament';

    protected string $description = <<<'TEXT'
    Finds New Testament verses by their original Greek wording, and returns the Greek text of every hit together with its Hungarian translation. Only the New Testament exists in Greek. Choose the mode that fits what you know:
    - `strong`: search by Strong number (e.g. "26"), the exact way to find every occurrence of one dictionary word. Follow up a number returned by get-greek-verses with this.
    - `lemma`: search by the latin transliteration of the dictionary form, without accents (e.g. "agape"), which finds every inflected form of the word.
    - `verse`: search in the Greek text as printed, for the word forms themselves (e.g. "ἀγάπη"); `|` means any of the words, `*` marks a word part.
    Keep the limit small and narrow the search by book instead of asking for many verses.
    TEXT;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()
                ->description('What to search for, interpreted according to the mode: a Strong number, a latin transliteration, or Greek text.')
                ->required(),
            'mode' => $schema->string()
                ->description('One of "strong", "lemma" or "verse". Defaults to "lemma".'),
            'rule' => $schema->string()
                ->description('For several words or Strong numbers: "all" (default) requires every one of them in the verse, "any" requires one. Ignored in "verse" mode, where `|` expresses the same.'),
            'book' => $schema->string()
                ->description('Restrict the search to a single New Testament book, given by its Hungarian abbreviation (e.g. "Jn") or USX code (e.g. "JHN"). Defaults to the whole New Testament.'),
            'translation' => $schema->string()
                ->description('The Hungarian translation shown next to the Greek text (e.g. RUF, SZIT). Defaults to the translation this endpoint is configured for.'),
            'limit' => $schema->integer()
                ->description('Maximum number of Greek verses to return, 1-'.self::MAX_LIMIT.'. Defaults to '.self::DEFAULT_LIMIT.'.'),
        ];
    }

    public function handle(Request $request, TranslationResolver $resolver, GreekSearchService $greekSearchService): Response
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'mode' => ['nullable', 'in:strong,lemma,verse'],
            'rule' => ['nullable', 'in:all,any'],
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

        $searchParams = new GreekSearchParams();
        $searchParams->text = $validated['text'];
        $searchParams->mode = GreekSearchMode::tryFrom($validated['mode'] ?? '') ?? GreekSearchMode::Lemma;
        $searchParams->rule = GreekSearchRule::tryFrom($validated['rule'] ?? '') ?? GreekSearchRule::All;
        $searchParams->translationId = $translation->id;
        $searchParams->usxCodes = $usxCodes;
        $searchParams->grouping = 'verse';
        $searchParams->limit = $validated['limit'] ?? self::DEFAULT_LIMIT;

        $verses = SearchResultFormatter::verses($greekSearchService->search($searchParams), withGreekText: true);
        $query = $this->describeQuery($validated, $searchParams, $translation->abbrev);

        if ($verses === []) {
            return Response::json(['query' => $query, 'totalHitCount' => 0, 'verses' => []]);
        }

        $totalHitCount = $greekSearchService->countHits($searchParams);

        return Response::json([
            'query' => $query,
            'totalHitCount' => $totalHitCount,
            'truncated' => $totalHitCount > count($verses),
            'verses' => $verses,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function describeQuery(array $validated, GreekSearchParams $searchParams, string $translationAbbrev): array
    {
        return [
            'text' => $searchParams->text,
            'mode' => $searchParams->mode->value,
            'rule' => $searchParams->rule->value,
            'book' => $validated['book'] ?? BookFilter::ALL,
            'translation' => $translationAbbrev,
            'limit' => $searchParams->resolvedLimit(),
        ];
    }
}
