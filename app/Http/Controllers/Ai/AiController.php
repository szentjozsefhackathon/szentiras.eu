<?php

namespace SzentirasHu\Http\Controllers\Ai;

use Exception;
use Illuminate\Http\Request;
use Pgvector\Vector;
use SzentirasHu\Data\UsxCodes;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\DictionaryEntry;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Search\SemanticSearchService;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\MorphologyService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;
use Illuminate\Support\Collection;

class AiController extends Controller
{

    public function __construct(
        protected TextService $textService,
        protected SemanticSearchService $semanticSearchService,
        protected TranslationService $translationService,
        protected ReferenceService $referenceService,
        protected BookService $bookService,
        protected MorphologyService $morphologyService
    ) {}

    public function getAiToolPopover($translationAbbrev, $reference)
    {
        $allTranslations = $this->translationService->getAllTranslations();
        $translation = $this->translationService->getByAbbreviation($translationAbbrev);
        $canonicalReference = CanonicalReference::fromString($reference, $translation->id);
        $gepi = $canonicalReference->toGepi();
        $greekVerse = GreekVerse::where('gepi', $gepi)->first();
        $greekVector = null;
        if ($greekVerse) {
            $annotatedGreekText = $greekVerse->annotatedWords();
            $greekVector = $this->semanticSearchService->retrieveGreekVector($greekVerse->gepi, $greekVerse->source);
        } else {
            $annotatedGreekText = null;
        }
        $vector1 = $this->semanticSearchService->retrieveVector($canonicalReference->toString(), $translationAbbrev);
        if ($vector1 && $greekVector) {
            $greekSimilarity = $this->semanticSearchService->calculateSimilarity($vector1, $greekVector);
        } else {
            $greekSimilarity = null;
        }
        $pureTexts = [];
        if ($translation->abbrev === 'GNT' && $greekVerse) {
            $pureTexts[] = [
                'translationAbbrev' => $translationAbbrev,
                'reference' => $canonicalReference->toString(),
                'text' => $greekVerse->text,
                'greekSimilarity' => $greekSimilarity
            ];
        } else {
            $pureTexts[] = [
                'translationAbbrev' => $translationAbbrev,
                'reference' => $canonicalReference->toString(),
                'text' => $this->textService->getPureText($canonicalReference, $translation, 'none'),
                'greekSimilarity' => $greekSimilarity
            ];
        }

        foreach ($allTranslations as $otherTranslation) {
            if ($otherTranslation->abbrev != $translationAbbrev) {
                $translatedReference = $this->referenceService->translateReference($canonicalReference, $otherTranslation->id)->toString();
                $otherText = $this->textService->getPureText(CanonicalReference::fromString($reference, $otherTranslation->id), $otherTranslation, 'none');
                if (!empty($otherText)) {
                    $vector2 = $this->semanticSearchService->retrieveVector($translatedReference, $otherTranslation->abbrev);
                    if ($vector1 && $vector2) {
                        $similarity = $this->semanticSearchService->calculateSimilarity($vector1, $vector2);
                    } else {
                        $similarity = null;
                    }
                    if ($vector2 && $greekVector) {
                        $translationGreekSimilarity = $this->semanticSearchService->calculateSimilarity($vector2, $greekVector);
                    } else {
                        $translationGreekSimilarity = null;
                    }
                    $pureTexts[] = [
                        'translationAbbrev' => $otherTranslation->abbrev,
                        'reference' => $translatedReference,
                        'text' => $otherText,
                        'similarity' => $similarity,
                        'greekSimilarity' => $translationGreekSimilarity
                    ];
                }
            }
        }
        // Get balanced results: 10 from OT and 10 from NT, sorted by relevance
        $similarExcerpts = $this->semanticSearchService->findSimilarVersesInTranslation($canonicalReference->toString(), $translationAbbrev, 10, true);
        $similarsOT = [];
        $similarsNT = [];
        if (!empty($similarExcerpts)) {
            foreach ($similarExcerpts as $excerpt) {
                /** @var \SzentirasHu\Data\Entity\EmbeddedExcerpt $excerpt */
                // Get translation ID from abbreviation
                $translation = $this->translationService->getByAbbreviation($excerpt->translation_abbrev);
                $similar = [
                    "reference" => $excerpt->reference,
                    "translationAbbrev" => $excerpt->translation_abbrev,
                    "similarity" => 1 - $excerpt->neighbor_distance,
                    "text" => $this->textService->getPureText(CanonicalReference::fromString($excerpt->reference, $translation->id), $translation, 'none')
                ];
                
                // Extract book abbreviation from reference to determine OT/NT
                $refParts = explode(' ', $excerpt->reference);
                $bookAbbrev = $refParts[0];
                $otUsxCodes = array_keys(UsxCodes::OLD_TESTAMENT);
                $ntUsxCodes = array_keys(UsxCodes::NEW_TESTAMENT);
                
                // Find matching USX code
                $matchingUsxCode = null;
                foreach ($otUsxCodes as $usxCode) {
                    if (in_array($bookAbbrev, UsxCodes::OLD_TESTAMENT[$usxCode]['default'] ?? [])) {
                        $matchingUsxCode = $usxCode;
                        break;
                    }
                }
                
                if (!$matchingUsxCode) {
                    foreach ($ntUsxCodes as $usxCode) {
                        if (in_array($bookAbbrev, UsxCodes::NEW_TESTAMENT[$usxCode]['default'] ?? [])) {
                            $matchingUsxCode = $usxCode;
                            break;
                        }
                    }
                }
                
                if (in_array($matchingUsxCode, $otUsxCodes)) {
                    if (count($similarsOT) < 10) {
                        $similarsOT[] = $similar;
                    }
                } else {
                    if (count($similarsNT) < 10) {
                        $similarsNT[] = $similar;
                    }
                }
            }
        }

        $view = view("ai.aiToolPopover", ['pureTexts' => $pureTexts, 'similarsOT' => $similarsOT, 'similarsNT' => $similarsNT, 'greekText' => $annotatedGreekText, 'greekSimilarity' => $greekSimilarity, 'gepi' => $gepi])->render();
        return response()->json($view);
    }

    public function getGreekWordPanel($usx_code, $chapter, $verse, $i)
    {
        $greekVerse = GreekVerse::where('usx_code', $usx_code)->where('chapter', $chapter)->where('verse', $verse)->first();
        $json = json_decode($greekVerse->json)[$i];
        $strongNumber = $json->strong;
        $strongWord = StrongWord::where('number', $strongNumber)->first();
        $dictEntry = DictionaryEntry::where('strong_word_number', $strongNumber)->first();
        $meanings = DictionaryMeaning::where('strong_word_number', $strongNumber)->orderBy('order')->get();
        $greekText = str_replace('¶', '', $greekVerse->text);
        $explodedText = explode(' ', $greekText);
        $printed = preg_replace('/[^\w]/u', '', $explodedText[$i]);
        $morphology = $this->morphologyService->describe($json->morphology);
        $view = view(
            "ai.greekWordPanel",
            [
                'morphology' => $morphology,
                'strongWord' => $strongWord,
                'printed' => $printed,
                'dictEntry' => $dictEntry,
                'meanings' => $meanings,
                'gepi' => $greekVerse->gepi
            ]
        )->render();
        return response()->json($view);
    }

    /**
     * Returns the first Hungarian meaning of every Greek word in a verse, keyed by
     * the word index `i` used by {@see GreekVerse::annotatedWords()}. Powers the
     * inline word-by-word translation toggled from the verse popover.
     *
     * @return \Illuminate\Http\JsonResponse JSON object mapping word index to its first meaning (or null when unknown).
     */
    public function getGreekVerseWordTranslations(string $usx_code, int $chapter, int $verse): \Illuminate\Http\JsonResponse
    {
        $greekVerse = GreekVerse::where('usx_code', $usx_code)->where('chapter', $chapter)->where('verse', $verse)->firstOrFail();
        $words = json_decode($greekVerse->json) ?: [];

        $strongNumbers = collect($words)->pluck('strong')->filter()->unique()->values()->all();

        $firstMeanings = [];
        if (!empty($strongNumbers)) {
            $meanings = DictionaryMeaning::whereIn('strong_word_number', $strongNumbers)
                ->orderBy('order')
                ->get();
            foreach ($meanings as $meaning) {
                if (!isset($firstMeanings[$meaning->strong_word_number])) {
                    $firstMeanings[$meaning->strong_word_number] = $meaning->meaning;
                }
            }
        }

        $translations = [];
        foreach ($words as $i => $word) {
            $translations[$i] = $firstMeanings[(int) $word->strong] ?? null;
        }

        return response()->json($translations);
    }

    public function getAllInstancesOfGreekWord($strongNumber, ?int $offset = 0)
    {
        $limit = 50;
        $translation = $this->translationService->getDefaultTranslation();

        $strongWord = StrongWord::where('number', $strongNumber)->with('dictionaryMeanings')->with('dictionaryEntry')->first();
        $hitCount = $strongWord->greekVerses()->count();

        /**
         * @var Collection<GreekVerse>
         */
        $otherGreekVerses = $strongWord->greekVerses()
            ->join('books', function ($join) use ($translation) {
                $join->on('greek_verses.usx_code', '=', 'books.usx_code')
                    ->where('books.translation_id', '=', $translation->id);
            })
            ->select('greek_verses.*')
            ->orderBy('books.order', 'asc')
            ->orderBy('chapter', 'asc')
            ->orderBy('verse', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();


        $instances = [];
        if (!$otherGreekVerses->isEmpty()) {
            foreach ($otherGreekVerses as $greekVerse) {
                $explodedStrongs = explode(' ', $greekVerse->strongs);
                $markedIndexes = [];
                foreach ($explodedStrongs as $index => $explodedStrong) {
                    if ($explodedStrong == $strongWord->lemma) {
                        $markedIndexes[] = $index;
                    }
                }
                $book = $this->bookService->getBookByUsxCodeTranslation($greekVerse->usx_code, $translation->abbrev);
                $ref = CanonicalReference::fromString("{$book->abbrev} {$greekVerse->chapter},{$greekVerse->verse}", $translation->id);
                $pureText = $this->textService->getPureText($ref, $translation, 'none');
                $instances[] = ["book" => $book, "greekVerse" => $greekVerse, "annotatedWords" => $greekVerse->annotatedWords(), "markedIndexes" => $markedIndexes, "pureText" => $pureText, "ref" => $ref];
            }
        }

        return view("ai.allInstancesOfGreekWord", ['instances' => $instances, 'strongWord' => $strongWord, 'hitCount' => $hitCount, 'limit' => $limit, 'offset' => $offset]);
    }
}
