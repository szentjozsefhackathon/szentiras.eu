<?php

namespace SzentirasHu\Http\Controllers\Ai;

use Exception;
use Illuminate\Http\Request;
use League\CommonMark\Reference\Reference;
use Pgvector\Vector;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Search\SemanticSearchService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

class AiController extends Controller
{

    public function __construct(
        protected TextService $textService,
        protected SemanticSearchService $semanticSearchService,
        protected TranslationService $translationService,
        protected ReferenceService $referenceService
    ) {}

    public function getAiToolPopover($translationAbbrev, $reference)
    {
        $hash = md5($reference);
        $allTranslations = $this->translationService->getAllTranslations();
        $translation = $this->translationService->getByAbbreviation($translationAbbrev);
        $canonicalReference = CanonicalReference::fromString($reference, $translation->id);
        $pureTexts[] = [
            'translationAbbrev' => $translationAbbrev,
            'reference' => $canonicalReference->toString(),
            'text' => $this->textService->getPureText($canonicalReference, $translation, false),
        ];
        $usxVerseId = $canonicalReference->toUsxVerseId();
        $verseIdParts = explode('_', str_replace(':', '_', str_replace(' ', '_', $usxVerseId)));
        $greekVerse = GreekVerse::where('usx_code', $verseIdParts[0])->where('chapter', $verseIdParts[1])->where('verse', $verseIdParts[2])->first();
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
        } else {
            $annotatedGreekText = null;
        }

        $vector1 = $this->semanticSearchService->retrieveVector($canonicalReference->toString(), $translationAbbrev);
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
                    $pureTexts[] = [
                        'translationAbbrev' => $otherTranslation->abbrev,
                        'reference' => $translatedReference,
                        'text' => $otherText,
                        'similarity' => $similarity
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

        $view = view("ai.aiToolPopover", ['pureTexts' => $pureTexts ?? [], 'similars' => $similars ?? [], 'greekText' => $annotatedGreekText, 'hash' => $hash])->render();
        return response()->json($view);
    }

    public function getGreekWordPanel($usx_code, $chapter, $verse, $i)
    {
        $greekVerse = GreekVerse::where('usx_code', $usx_code)->where('chapter', $chapter)->where('verse', $verse)->first();
        $json = json_decode($greekVerse->json)[$i];
        $strongWord = StrongWord::where('number', $json->strong)->first();
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
            ]

        )->render();
        return response()->json($view);
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
     * @param string $code The morph code starting with "V-"
     * @return string Explanation in Hungarian.
     */
    private function parseMorphology($morphCode)
    {
        $undeclined = [
            "ADV"  => "határozószó",
            "CONJ" => "kötőszó",
            "COND" => "feltételes kötőszó",
            "PRT" => "indulatszó",
            "PREP" => "elöljárószó",
            "INJ" => "felkiáltószó",
            "ARAM" => "arámi",
            "HEB" => "héber",
            "N-PRI" => "nem ragozható tulajdonnév",
            "N-NSM" => "nem ragozható tulajdonnév",
            "N-LI" => "nem ragozható betű",
            "N-OI" => "nem ragozható főnév",
        ];

        $code = trim($morphCode);
        if (isset($undeclined[$code])) {
            return $undeclined[$code];
        } else if (strpos($code, "V-") === 0) {
            return $this->parseVerbMorphCode($code);
        } else {
            $parts = explode('-', $code);
            if (count($parts) !== 2) {
                return null;
            }
            $posTag = $parts[0];
            $attributes = $parts[1];
            $posLookup = [ 
                "N" => "főnév", 
                "A" => "melléknév", 
                "T" => "határozott névelő", 
                "R" => "vonatkozó névmás",
                "C" => "kölcsönös névmás", 
                "D" => "mutató névmás", 
                "K" => "korrelatív névmás", 
                "I" => "kérdő névmás",
                "X" => "határozatlan névmás",
                "Q" => "korrelatív vagy kérdő névmás",
                "F" => "visszaható névmás",
                "S" => "birtokos névmás",
                "P" => "személyes névmás"
                ];


            $caseLookup = ["N" => "alanyeset", "G" => "birtokos eset", "D" => "részes eset", "A" => "tárgyeset", "V" => "megszólító eset"];
            $numberLookup = ["S" => "egyes szám", "P" => "többes szám"];
            $genderLookup = ["M" => "hímnem", "F" => "nőnem", "N" => "semlegesnem"];

            if (strlen($attributes) !== 3) {
                return null;
            }

            $caseCode   = substr($attributes, 0, 1);
            $numberCode = substr($attributes, 1, 1);
            $genderCode = substr($attributes, 2, 1);

            // Felépítjük az eredmény tömböt.
            $result[] = " " . $posLookup[$posTag] ?? "";
            $result[] = " " . $caseLookup[$caseCode] ?? "";
            $result[] = " " . $numberLookup[$numberCode] ?? "";
            $result[] = " " . $genderLookup[$genderCode] ?? "";

            return implode(',', $result);
        }
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
     * @param string $code The morph code starting with "V-"
     * @return string Explanation in Hungarian.
     */
    function parseVerbMorphCode($code)
    {
        $code = trim($code);
        $attic = false;

        // Explode on the hyphen.
        // (A verb code might contain extra parts; for example: V-2AOM-3P-ATT)
        $parts = explode('-', $code);

        // Check for an extra ATT part. If the last part equals "ATT" (case‐insensitive), mark it and remove it.
        if (count($parts) > 2 && strtoupper(end($parts)) === 'ATT') {
            $attic = true;
            array_pop($parts);
        }

        if (count($parts) < 2 || $parts[0] !== 'V') {
            return null;
        }

        // The second part contains the concatenated Tense, Voice, Mood.
        // Note: If the tense is one of the "Second" forms, it will begin with a "2" (e.g. "2F", "2A", "2R", "2L").
        $base = $parts[1];
        $tense = "";
        $voice = "";
        $mood  = "";

        if (substr($base, 0, 1) === '2') {
            // Tense is two characters (for "2F", "2A", etc.)
            $tense = substr($base, 0, 2);
            $voice = substr($base, 2, 1);
            $mood  = substr($base, 3, 1);
        } else {
            $tense = substr($base, 0, 1);
            $voice = substr($base, 1, 1);
            $mood  = substr($base, 2, 1);
        }

        // Mapping arrays for each category.
        $tenseMap = [
            "P"  => "jelen idő",
            "I"  => "imperfektum",
            "F"  => "jövő idő",
            "2F" => "második jövő idő",
            "A"  => "aoristus",
            "2A" => "második aoristus",
            "R"  => "perfectum",
            "2R" => "második perfectum",
            "L"  => "pluperfectum",
            "2L" => "második pluperfectum"
        ];
        $voiceMap = [
            "A" => "aktív",
            "M" => "közép",
            "P" => "passzív",
            "E" => "középmód/passzív",
            "D" => "közép deponens",
            "O" => "passzív deponens",
            "N" => "közép/passzív deponens"
        ];

        $moodMap = [
            "I" => "kijelentő mód",
            "S" => "szubjunktív mód",
            "O" => "optatív mód",
            "M" => "felszólító mód",
            "N" => "infinitívus",
            "P" => "melléknévi igenév"
        ];

        $personMap = [
            "1" => "első személy",
            "2" => "második személy",
            "3" => "harmadik személy"
        ];

        $numberMap = [
            "S" => "egyes szám",
            "P" => "többes szám"
        ];

        // For participial forms (third format), the extra codes are case, number, and gender.
        $caseMap = [
            "N" => "alanyeset",
            "G" => "birtokos eset",
            "D" => "részes eset",
            "A" => "tárgyeset"
        ];

        $genderMap = [
            "M" => "hímnem",
            "F" => "nőnem",
            "N" => "semleges nem"
        ];

        // Start assembling the Hungarian explanation.
        $explanationParts = [];
        $explanationParts[] = "ige";  // "Verb" in Hungarian

        // Tense
        $tenseExp = isset($tenseMap[$tense]) ? $tenseMap[$tense] : "";
        $explanationParts[] = $tenseExp;

        // Voice
        $voiceExp = isset($voiceMap[$voice]) ? $voiceMap[$voice] : "";
        $explanationParts[] = $voiceExp;

        // Mood
        $moodExp = isset($moodMap[$mood]) ? $moodMap[$mood] : "";
        $explanationParts[] = $moodExp;

        // If an extra part (either person/number or case/number/gender) exists.
        if (isset($parts[2])) {
            $extra = $parts[2];
            // If exactly 2 characters, interpret as person-number.
            if (strlen($extra) === 2) {
                $person = substr($extra, 0, 1);
                $num    = substr($extra, 1, 1);
                $personExp = $personMap[$person] ?? "";
                $numberExp = $numberMap[$num]  ?? "";
                $explanationParts[] = $personExp;
                $explanationParts[] = $numberExp;
            }
            // If exactly 3 characters, interpret as case-number-gender (typically for participles).
            else if (strlen($extra) === 3) {
                $case   = substr($extra, 0, 1);
                $num    = substr($extra, 1, 1);
                $gender = substr($extra, 2, 1);
                $caseExp   = $caseMap[$case] ?? "";
                $numberExp = $numberMap[$num] ?? "";
                $genderExp = $genderMap[$gender] ?? "";
                $explanationParts[] = $caseExp;
                $explanationParts[] = $numberExp;
                $explanationParts[] = $genderExp;
            }
        }

        if ($attic) {
            $explanationParts[] = "attikus alak";
        }

        return implode(", ", $explanationParts);
    }
}
