<?php

namespace SzentirasHu\Http\Controllers\Ai;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use League\CommonMark\Reference\Reference;
use Pgvector\Vector;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\DictionaryEntry;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Search\SemanticSearchService;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;
use Illuminate\Support\Collection;
use Log;

class AiController extends Controller
{

    public function __construct(
        protected TextService $textService,
        protected SemanticSearchService $semanticSearchService,
        protected TranslationService $translationService,
        protected ReferenceService $referenceService,
        protected BookService $bookService
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
            $annotatedGreekText = [];
            $greekText = str_replace('¶', '', $greekVerse->text);
            $explodedText = explode(' ', $greekText);
            $explodedStrongs = explode(' ', $greekVerse->strongs);
            $explodedTranslits = explode(' ', $greekVerse->strong_transliterations);
            foreach ($explodedText as $i => $word) {
                $annotatedGreekText[] = [
                    'printed' => $word,
                    'strong' => $explodedStrongs[$i] ?? null,
                    'translit' => $explodedTranslits[$i] ?? null,
                    'usx_code' => $greekVerse->usx_code,
                    'chapter' => $greekVerse->chapter,
                    'verse' => $greekVerse->verse,
                    'i' => $i
                ];
            }
            $greekVector = $this->semanticSearchService->retrieveGreekVector($greekVerse->gepi);
        } else {
            $annotatedGreekText = null;
        }
        $vector1 = $this->semanticSearchService->retrieveVector($canonicalReference->toString(), $translationAbbrev);
        if ($vector1 && $greekVector) {
            $greekSimilarity = $this->semanticSearchService->calculateSimilarity($vector1, $greekVector);
        } else {
            $greekSimilarity = null;
        }
        $pureTexts[] = [
            'translationAbbrev' => $translationAbbrev,
            'reference' => $canonicalReference->toString(),
            'text' => $this->textService->getPureText($canonicalReference, $translation, false),
            'greekSimilarity' => $greekSimilarity
        ];

        foreach ($allTranslations as $otherTranslation) {
            if ($otherTranslation->abbrev != $translationAbbrev) {
                $translatedReference = $this->referenceService->translateReference($canonicalReference, $otherTranslation->id)->toString();
                $otherText = $this->textService->getPureText(CanonicalReference::fromString($reference, $otherTranslation->id), $otherTranslation, false);
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
        $similarExcerpts = $this->semanticSearchService->findSimilarVersesInTranslation($canonicalReference->toString(), $translationAbbrev);
        if (!empty($similarExcerpts)) {
            foreach ($similarExcerpts as $excerpt) {
                $similars[] = [
                    "reference" => $excerpt->reference,
                    "translationAbbrev" => $excerpt->translation_abbrev,
                    "similarity" => 1 - $excerpt->neighbor_distance,
                    "text" => $this->textService->getPureText(CanonicalReference::fromString($excerpt->reference, $excerpt->translation_id), $this->translationService->getByAbbreviation($excerpt->translation_abbrev), false)
                ];
            }
        }

        $view = view("ai.aiToolPopover", ['pureTexts' => $pureTexts, 'similars' => $similars ?? [], 'greekText' => $annotatedGreekText, 'greekSimilarity' => $greekSimilarity, 'gepi' => $gepi])->render();
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
        $morphology = $this->parseMorphology($json->morphology);
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
                $greekText = str_replace('¶', '', $greekVerse->text);
                $explodedStrongs = explode(' ', $greekVerse->strongs);
                $strongIndexes = [];
                foreach ($explodedStrongs as $index => $explodedStrong) {
                    if ($explodedStrong == $strongWord->lemma) {
                        $strongIndexes[] = $index;
                    }
                }
                $explodedGreekText = explode(' ', $greekText);
                foreach ($strongIndexes as $index) {
                    if (array_key_exists($index, $explodedGreekText)) {
                        $explodedGreekText[$index] = "<mark>{$explodedGreekText[$index]}</mark>";
                    } else {
                        Log::debug('Wrong strong index in verse');
                    }
                }
                $greekText = implode(' ', $explodedGreekText);
                $book = $this->bookService->getBookByUsxCodeTranslation($greekVerse->usx_code, $translation->abbrev);
                $ref = CanonicalReference::fromString("{$book->abbrev} {$greekVerse->chapter},{$greekVerse->verse}", $translation->id);
                $pureText = $this->textService->getPureText($ref, $translation, false);
                $instances[] = ["book" => $book, "greekVerse" => $greekVerse, "greekText" => $greekText, "pureText" => $pureText, "ref" => $ref];
            }
        }

        return view("ai.allInstancesOfGreekWord", ['instances' => $instances, 'strongWord' => $strongWord, 'hitCount' => $hitCount, 'limit' => $limit, 'offset' => $offset]);
    }


    /**
     * Parses a Greek verb morphological code and returns a Hungarian explanation.
     *
     * Verb codes have one of three formats:
     *   1. V‑tense‑voice‑mood
     *   2. V‑tense‑voice‑mood‑person‑number
     *   3. V‑tense‑voice‑mood‑case‑number‑gender
     *
     * An optional trailing “ATT” indicates an Attic form.
     *
     * Examples:
     *   V-PAI-1S → "ige, jelen idő, aktív, kijelentő mód, első személy, egyes szám"
     *   V-2AOM-3P-ATT → "ige, második aoristus, passzív deponens, felszólító mód, harmadik személy, többes szám, attikus alak"
     *
     * @param string $morphCode The morph code starting with "V-"
     * @return string Explanation in Hungarian.
     */
    private function parseMorphology($morphCode): string
    {
        $morphology = Config::get("morphology.{$morphCode}");
        $result = [];
        if ($morphology) {
            $result[] = $morphology['partOfSpeech'] ?? null;
            $result[] = $morphology['tense'] ?? null;
            $result[] = $morphology['voice'] ?? null;
            $result[] = $morphology['mood'] ?? null;
            $result[] = $morphology['number'] ?? null;
            $result[] = $morphology['person'] ?? null;
            $result[] = $morphology['case'] ?? null;
            $result[] = $morphology['gender'] ?? null;
            $result[] = $morphology['degree'] ?? null;
            $result[] = $morphology['form'] ?? null;
            return implode(", ", array_filter($result));
        } else {
            return "";
        }
    }
}
