<?php

namespace SzentirasHu\Http\Controllers\Display;

use Cache;
use Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Redirect;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ChapterRequestGuard;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\VerseContainer;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Data\Repository\VerseRepository;
use SzentirasHu\Data\Repository\ReadingPlanRepository;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\Media;
use SzentirasHu\Data\Entity\Place;
use SzentirasHu\Data\Entity\PlaceVerse;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;
use View;
use SzentirasHu\Service\Reference\NumberingSchemeService;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Service\Ai\CommentaryService;


/**
 *
 * @author berti
 */
class TextDisplayController extends Controller
{


    /**
     * @var \SzentirasHu\Data\Repository\TranslationRepository
     */
    private $translationRepository;
    /**
     * @var \SzentirasHu\Data\Repository\BookRepository
     */
    private $bookRepository;
    /**
     * @var \SzentirasHu\Data\Repository\VerseRepository
     */
    private $verseRepository;
    /**
     * @var \SzentirasHu\Data\Repository\ReadingPlanRepository
     */
    private $readingPlanRepository;

    private $referenceService;
    /**
     * @var \SzentirasHu\Service\Text\TextService
     */
    private $textService;

    /**
     * @var \SzentirasHu\Service\Reference\NumberingSchemeService
     */
    private $numberingSchemeService;

    /**
     * @var \SzentirasHu\Service\Ai\CommentaryService
     */
    private $commentaryService;
    function __construct(TranslationRepository $translationRepository, BookRepository $bookRepository, VerseRepository $verseRepository, ReadingPlanRepository $readingPlanRepository, ReferenceService $referenceService, TextService $textService, NumberingSchemeService $numberingSchemeService, CommentaryService $commentaryService, protected BookService $bookService, protected TranslationService $translationService, protected EditorService $editorService, protected ChapterRequestGuard $chapterRequestGuard)
    {
        $this->translationRepository = $translationRepository;
        $this->bookRepository = $bookRepository;
        $this->verseRepository = $verseRepository;
        $this->readingPlanRepository = $readingPlanRepository;
        $this->referenceService = $referenceService;
        $this->textService = $textService;
        $this->numberingSchemeService = $numberingSchemeService;
        $this->commentaryService = $commentaryService;
    }

    private function commentaryGenerationPossible(): bool
    {
        return $this->commentaryService->commentaryGenerationPossible();
    }

    private function canGenerateCommentary(): bool
    {
        return $this->commentaryService->canGenerateCommentary($this->editorService->currentIsEditor());
    }

    public function showTranslationList()
    {
        $translations = $this->translationRepository->getAllOrderedByDenom();
        return View::make('textDisplay.translationList', [
            'translations' => $translations
        ]);
    }

    public function showTranslation($translationAbbrev)
    {
        $allTranslation = $this->translationRepository->getAll();
        $translation = $this->translationRepository->getByAbbrev($translationAbbrev);
        if (!$allTranslation->contains($translation)) {
            return $this->handleDisabledTranslations($translation);
        }
        $books = $this->translationRepository->getBooks($translation);
        $bookHeaders = [];
        $toc = request()->has("toc");
        if ($toc) {
            $bookHeaders = Cache::rememberForever("bookHeaders_{$translationAbbrev}", function () use ($books, $translation) {
                $result = [];
                foreach ($books as $book) {
                    $canonicalRef = CanonicalReference::fromString("{$book->abbrev}", $translation->id);
                    $verses = $this->textService->getTranslatedVerses($canonicalRef, $translation, Verse::getHeadingTypes($translation->abbrev));
                    $result[$book->abbrev] = $this->getBookViewArray($book, $verses, $translation, $canonicalRef, $canonicalRef, false);
                }
                return $result;
            });
        }
        return View::make(
            'textDisplay.translation',
            [
                'translation' => $translation,
                'books' => $books,
                'bookHeaders' => $bookHeaders,
                'toc' => $toc,
                'teaser' => "A(z) {$translation->name} ({$translation->abbrev}) bibliafordítás online olvasható a Szentíráson: az Ó- és Újszövetség könyvei fejezetenként, kereséssel és más magyar fordításokkal párhuzamosan.",
            ]
        );
    }

    public function showReferenceText($reference)
    {
        return $this->showTranslatedReferenceText(null, $reference);
    }

    public function showXrefText($translationAbbrev, $reference)
    {
        $translation = $this->translationRepository->getByAbbrev($translationAbbrev ? $translationAbbrev : Config::get('settings.defaultTranslationAbbrev'));
        $canonicalRef = CanonicalReference::fromString($reference, $translation->id);
        $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation);
        $view = view('textDisplay.xrefText', ['verseContainers' => $verseContainers, 'translation' => $translation])->render();
        return response()->json($view);
    }

    private function handleDisabledTranslations($translation) {
        return response()->view("textDisplay.translationNotEnabled", ['translation' => $translation, 'translationInfo' => \Config::get("translations.definitions.{$translation->abbrev}")], 404);
    }

    public function showTranslatedReferenceText($translationAbbrev, $reference, $previousDay = null, $readingPlanDay = null, $nextDay = null)
    {
        try {
            $translation = $this->translationRepository->getByAbbrev($translationAbbrev ? $translationAbbrev : Config::get('settings.defaultTranslationAbbrev'));
            $allTranslation = $this->translationRepository->getAll();
            if (!$allTranslation->contains($translation)) {
                return $this->handleDisabledTranslations($translation);
            }
            $canonicalRef = CanonicalReference::fromString($reference, $translation->id);
            $scheme = request()->query('scheme', 'default');
            if ($scheme === 'vulgata') {
                $canonicalRef = $this->numberingSchemeService->convertReference($canonicalRef, 'vulgata', 'default');
            }
            if ($this->chapterRequestGuard->exceedsLimit($canonicalRef, $translation)) {
                abort(413, 'Egyszerre legfeljebb 5 fejezet kérhető. Kérjük, bontsd a hivatkozást kisebb részekre.');
            }
            if ($canonicalRef->isBookLevel()) {
                return $this->bookView($translationAbbrev, $canonicalRef);
            }
            $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation);
            if (empty($verseContainers) || sizeof($verseContainers) == 1 && empty($verseContainers[0]->rawVerses)) {
                $defaultTranslation = $this->translationService->getDefaultTranslation();
                $defaultCanonicalRef = $this->referenceService->translateReference($canonicalRef, $defaultTranslation->id);
                $verseContainers = $this->textService->getTranslatedVerses($defaultCanonicalRef, $defaultTranslation);
                if (empty($verseContainers) || sizeof($verseContainers) == 1 && empty($verseContainers[0]->rawVerses)) {
                    abort(404);
                } else {
                    return $this->referenceFallback($translation, $defaultTranslation, $defaultCanonicalRef);
                }
            }
            $chapterLinks = $canonicalRef->isOneChapter() ?
                $this->createChapterLinks($canonicalRef, $translation)
                : false;
            $fullChaptersIncluded = true;
            foreach ($verseContainers as $verseContainer) {
                $bookRef = $verseContainer->bookRef;
                if (count($bookRef->chapterRanges) > 1) {
                    $fullChaptersIncluded = false;
                    break;
                } else {
                    $chapterRange = $bookRef->chapterRanges[0];
                    if (
                        $chapterRange->untilChapterRef !== null &&
                        $chapterRange->chapterRef->chapterId != $chapterRange->untilChapterRef->chapterId
                        && (!empty($chapterRange->chapterRef->verseRanges) || !empty($chapterRange->untilChapterRef->verseRanges))
                    ) {
                        $fullChaptersIncluded = false;
                        break;
                    } else if (!empty($chapterRange->chapterRef->verseRanges)) {
                        $fullChaptersIncluded = false;
                        break;
                    }
                }
            }
            $fullContext = request()->has("fullContext");
            if ($fullContext) {
                // Collect chapter numbers from verse containers
                $chapterNumbers = [];
                foreach ($verseContainers as $verseContainer) {
                    $chapterNumbers[$verseContainer->bookRef->bookId] = array_merge($verseContainer->bookRef->getIncludedChapters(), $chapterNumbers[$verseContainer->bookRef->bookId] ?? []);
                    $chapterNumbers[$verseContainer->bookRef->bookId] = array_unique($chapterNumbers[$verseContainer->bookRef->bookId]);
                    // sort the array
                    sort($chapterNumbers[$verseContainer->bookRef->bookId]);
                }
                // Create a new canonical reference with the collected chapter numbers
                // ["Mt" => [1,2], "Mk" => [2,3]] should be "Mt1;2;3;Mk3"
                $chapterReferences = [];
                foreach ($chapterNumbers as $bookId => $chapters) {
                    $chapterReferences[] = $bookId . implode(';', $chapters);
                }
                $chapterReferenceString = implode(';', $chapterReferences);
                $fullContextVerseContainers = $this->textService->getTranslatedVerses(CanonicalReference::fromString($chapterReferenceString, $translation->id), $translation);
                $highlightedGepis = [];
                foreach ($verseContainers as $verseContainer) {
                    $highlightedGepis = array_merge($highlightedGepis, array_map(fn($k) => "{$k}", array_keys($verseContainer->rawVerses)));
                }
            }
            $chapterMedia = [];
            $hasMedia = false;
            foreach ($verseContainers as $verseContainer) {
                foreach ($verseContainer->getParsedVerses() as $verseData) {
                    $key = "{$verseData->book->usx_code}_{$verseData->chapter}";
                    if (array_key_exists($key, $chapterMedia)) {
                        continue;
                    }
                    $hasMedia = Media::where('usx_code', $verseData->book->usx_code)
                        ->where('chapter', $verseData->chapter)
                        ->exists();
                    $chapterMedia[$key] = $hasMedia;
                }
            }

            $mediaEnabled = request()->has("media");
            if ($mediaEnabled) {
                $mediaVerses = [];
                foreach ($verseContainers as $verseContainer) {
                    foreach ($verseContainer->getParsedVerses() as $verseData) {
                        $key = "{$verseData->book->usx_code}_{$verseData->chapter}";
                        if (array_key_exists($key, $chapterMedia) && $chapterMedia[$key] === true) {
                            $media = Media::where('usx_code', $verseData->book->usx_code)
                                ->where('chapter', $verseData->chapter)
                                ->get();
                            if (!$media->isEmpty()) {
                                // now we have the media for the whole chapter
                                $chapterMedia[$key] = $media;
                            }
                        }
                    }
                }

                foreach ($chapterMedia as $book_chapter => $mediaItems) {
                    if (is_bool($mediaItems)) {
                        continue;
                    }
                    $bookNumber = explode("_", $book_chapter)[0];
                    $book = $this->bookService->getBookByUsxCodeTranslation($bookNumber, $translation->abbrev);
                    $chapterNumber = (int) explode("_", $book_chapter)[1];
                    $verseMedia = [];
                    foreach ($mediaItems as $mediaItem) {
                        $verseMedia[$mediaItem->verse][] = $mediaItem;
                    }
                    // now we have media items for all verses in the chapter
                    foreach ($verseMedia as $verse => $mediaItems) {
                        $chapterLength = $this->bookService->getVerseCount($book, $chapterNumber, $translation);
                        if ($verse <= $chapterLength) {
                            $mediaVerses["{$bookNumber}_{$chapterNumber}_{$verse}"] = $mediaItems;
                        } else {
                            $otherMedia["{$bookNumber}_{$chapterNumber}"] = array_merge($otherMedia["{$bookNumber}_{$chapterNumber}"] ?? [], $mediaItems);
                        }
                    }
                }
            }

            // Commentaries are loaded client-side from /api/commentaries/content so
            // these pages stay safe for the CDN to cache (see CommentaryEditorController).
            $displayContainers = $fullContextVerseContainers ?? $verseContainers;

            // Fetch places for each verse container
            $parsedPlaces = [];
            $verseToPlaces = [];
            $verseTriples = [];
            $tripleToContainer = [];
            $containerIndex = 0;
            foreach ($displayContainers as $verseContainer) {
                foreach ($verseContainer->getParsedVerses() as $verseData) {
                    $key = $verseData->book->usx_code . ':' . $verseData->chapter . ':' . $verseData->numv;
                    $verseTriples[$key] = [
                        'book_code' => $verseData->book->usx_code,
                        'chapter_number' => $verseData->chapter,
                        'verse_number' => $verseData->numv,
                    ];
                    $tripleToContainer[$key] = $containerIndex;
                }
                $containerIndex++;
            }

            if (!empty($verseTriples)) {
                $placeVerses = PlaceVerse::with(['place' => fn ($query) => $query->withCoordinates()])
                    ->where(function ($query) use ($verseTriples) {
                        foreach ($verseTriples as $triple) {
                            $query->orWhere(function ($q) use ($triple) {
                                $q->where('book_code', $triple['book_code'])
                                    ->where('chapter_number', $triple['chapter_number'])
                                    ->where('verse_number', $triple['verse_number']);
                            });
                        }
                    })
                    ->get();

                // Group by container index and by verse
                $placesByContainer = [];
                foreach ($placeVerses as $placeVerse) {
                    // Skip places without coordinates
                    if (!$placeVerse->place || !$placeVerse->place->lon_lat) {
                        continue;
                    }

                    $key = $placeVerse->book_code . ':' . $placeVerse->chapter_number . ':' . $placeVerse->verse_number;
                    if (isset($tripleToContainer[$key])) {
                        $index = $tripleToContainer[$key];
                        $placesByContainer[$index][] = $placeVerse;

                        // Also map by verse gepi for inline display
                        $gepi = $placeVerse->book_code . '_' . $placeVerse->chapter_number . '_' . $placeVerse->verse_number;
                        if (!isset($verseToPlaces[$gepi])) {
                            $verseToPlaces[$gepi] = [];
                        }
                        $verseToPlaces[$gepi][] = [
                            'type' => $placeVerse->place->type,
                            'friendly_id' => $placeVerse->place->friendly_id,
                            'comment' => $placeVerse->place->comment,
                            'lon_lat' => $placeVerse->place->lon_lat,
                            'place_id' => $placeVerse->place->id,
                        ];
                    }
                }

                // Build parsed places array
                for ($i = 0; $i < $containerIndex; $i++) {
                    $places = $placesByContainer[$i] ?? [];
                    $parsed = [];
                    foreach ($places as $placeVerse) {
                        $place = $placeVerse->place;
                        $parsed[] = [
                            'type' => $place->type,
                            'friendly_id' => $place->friendly_id,
                            'comment' => $place->comment,
                            'lon_lat' => $place->lon_lat,
                            'place_id' => $place->id,
                        ];
                    }
                    $parsedPlaces[] = $parsed;
                }
            } else {
                // No verses, fill with empty arrays
                for ($i = 0; $i < $containerIndex; $i++) {
                    $parsedPlaces[] = [];
                }
            }

            $scrollTo = $canonicalRef->toGepi();

            // Handle comparison translation
            $compareAbbrev = request()->query('compare');
            $compareTranslation = null;
            $compareVerseContainers = null;
            $compareGreekVerses = null;

            if ($compareAbbrev) {
                $compareTranslation = $this->translationRepository->getByAbbrev($compareAbbrev);
                if ($compareTranslation && $allTranslation->contains($compareTranslation) && $compareAbbrev !== $translationAbbrev) {
                    if ($compareAbbrev === 'GNT') {
                        // GNT verses are stored separately from regular translations, so we align
                        // the Greek text to the verses being displayed in the primary translation.
                        $compareGreekVerses = $this->getGreekVersesForContainers($displayContainers);
                    } else {
                        $compareCanonicalRef = CanonicalReference::fromString($reference, $compareTranslation->id);
                        $compareVerseContainers = $this->textService->getTranslatedVerses($compareCanonicalRef, $compareTranslation);
                    }
                }
            }

            $translations = $this->translationRepository->getAllOrderedByDenom();
            $pageHeading = $this->getChapterPageHeading($verseContainers);

            return View::make('textDisplay.verses')->with([
                'fullChaptersIncluded' => $fullChaptersIncluded,
                'highlightedGepis' => $highlightedGepis ?? [],
                'fullContext' => $fullContext,
                'scrollTo' => $fullContext ? $scrollTo : null,
                 'mediaEnabled' => $mediaEnabled,
                'hasMedia' => $hasMedia,
                'previousDay' => $previousDay,
                'readingPlan' => $readingPlanDay ? $readingPlanDay->plan : null,
                'readingPlanDay' => $readingPlanDay,
                'nextDay' => $nextDay,
                'canonicalRef' => str_replace(" ", "%20", $canonicalRef->toString()),
                'verseContainers' => $fullContextVerseContainers ?? $verseContainers,
                'parsedPlaces' => $parsedPlaces,
                'verseToPlaces' => $verseToPlaces,
                'translation' => $translation,
                'translations' => $translations,
                'canonicalUrl' => $this->referenceService->getCanonicalUrl($canonicalRef, $translation->id),
                'seoUrl' => $this->referenceService->getSeoUrl($canonicalRef, $translation->id),
                'metaTitle' => $this->getTitle($verseContainers, $translation, $pageHeading),
                'metaReference' => $this->getReference($verseContainers),
                'pageHeading' => $pageHeading,
                'teaser' => $this->buildMetaDescription($verseContainers, $translation),
                'chapterLinks' => $chapterLinks,
                'media' => $mediaVerses ?? [],
                'otherMedia' => $otherMedia ?? [],
                'isEditor' => $this->editorService->currentIsEditor(),
                'canGenerateCommentary' => $this->canGenerateCommentary(),
                'commentaryGenerationPossible' => $this->commentaryGenerationPossible(),
                'translationLinks' => $translations->map(
                    function ($otherTranslation) use ($canonicalRef, $translation) {
                        $allBooksExistInTranslation = true;
                        foreach ($canonicalRef->bookRefs as $bookRef) {
                            $book = $this->bookRepository->getByAbbrevForTranslation($bookRef->bookId, $translation);
                            if (!$this->getAllBookTranslations($book->usx_code)->contains($otherTranslation->id)) {
                                $allBooksExistInTranslation = false;
                                break;
                            }
                        }

                        // Special handling for GNT translation
                        $link = $this->referenceService->getCanonicalUrl($canonicalRef, $otherTranslation->id);
                        if ($otherTranslation->abbrev === 'GNT' && $allBooksExistInTranslation) {
                            // For GNT, we need to link to the GreekTextController with the full reference
                            $link = "/GNT/{$canonicalRef->toString()}";
                        }

                        return [
                            'id' => $otherTranslation->id,
                            'link' => $link,
                            'abbrev' => $otherTranslation->abbrev,
                            'enabled' => $allBooksExistInTranslation
                        ];
                    }
                )->sortBy(function ($translationLink) {
                    // Put GNT at the end
                    return $translationLink['abbrev'] === 'GNT' ? 1 : 0;
                })->values(),
                'compareTranslation' => $compareTranslation,
                'compareVerseContainers' => $compareVerseContainers,
                'compareGreekVerses' => $compareGreekVerses
            ]);
        } catch (ParsingException $e) {
            // as this doesn't look like a valid reference
            abort(404);
        }
    }

    /**
     * Collect the Greek (GNT) verses that line up with the verses displayed in the primary
     * translation, grouped per verse container so the comparison view can render them side by side.
     *
     * @param VerseContainer[] $verseContainers
     * @return array<int, \Illuminate\Support\Collection<int, GreekVerse>>
     */
    private function getGreekVersesForContainers(array $verseContainers): array
    {
        $compareGreekVerses = [];
        foreach ($verseContainers as $verseContainer) {
            $triples = [];
            foreach ($verseContainer->getParsedVerses() as $verseData) {
                $triples[] = [
                    'usx_code' => $verseData->book->usx_code,
                    'chapter' => $verseData->chapter,
                    'verse' => $verseData->numv,
                ];
            }

            if (empty($triples)) {
                $compareGreekVerses[] = collect();
                continue;
            }

            $compareGreekVerses[] = GreekVerse::query()
                ->withVerseAnalysisAvailability()
                ->where(function ($query) use ($triples) {
                    foreach ($triples as $triple) {
                        $query->orWhere(function ($subQuery) use ($triple) {
                            $subQuery->where('usx_code', $triple['usx_code'])
                                ->where('chapter', $triple['chapter'])
                                ->where('verse', $triple['verse']);
                        });
                    }
                })
                ->orderBy('chapter')
                ->orderBy('verse')
                ->get();
        }

        return $compareGreekVerses;
    }

    public function showReadingPlanList()
    {
        $readingPlans = $this->readingPlanRepository->getAll();
        return View::make('textDisplay.readingPlanList', [
            'readingPlans' => $readingPlans
        ]);
    }

    public function showReadingPlan($id)
    {
        $readingPlan = $this->readingPlanRepository->getReadingPlanByPlanId($id);
        return View::make('textDisplay.readingPlanDayList', [
            'readingPlan' => $readingPlan
        ]);
    }

    public function showReadingPlanDay($planId, $dayNumber)
    {
        $readingPlan = $this->readingPlanRepository->getReadingPlanByPlanId($planId);
        if (!$readingPlan) {
            return Redirect::to('/');
        }

        $readingPlanDay = $readingPlan->days()->where('day_number', '=', $dayNumber)->first();
        if (!$readingPlanDay) {
            return Redirect::to('/');
        }

        $previousDay = $readingPlan->days()->where('day_number', '=', $dayNumber - 1)->first();
        $nextDay = $readingPlan->days()->where('day_number', '=', $dayNumber + 1)->first();

        return $this->showTranslatedReferenceText(null, $readingPlanDay->verses, $previousDay, $readingPlanDay, $nextDay);
    }

    private function bookView($translationAbbrev, CanonicalReference $canonicalRef)
    {
        $translation = $this->translationRepository->getByAbbrev($translationAbbrev ? $translationAbbrev : Config::get('settings.defaultTranslationAbbrev'));
        $translatedRef = $this->referenceService->translateReference($canonicalRef, $translation->id);
        $book = $this->bookRepository->getByAbbrevForTranslation($translatedRef->bookRefs[0]->bookId, $translation);
        if ($book) {
            return View::make('textDisplay.book', $this->getBookViewArray($book, $this->textService->getTranslatedVerses($canonicalRef, $translation), $translation, $canonicalRef, $translatedRef));
        } else {
            $defaultTranslation = $this->translationService->getDefaultTranslation();
            $defaultCanonicalRef = $this->referenceService->translateReference($canonicalRef, $defaultTranslation->id);
            return $this->referenceFallback($translation, $defaultTranslation, $defaultCanonicalRef);
        }
    }

    private function referenceFallback(Translation $requestedTranslation, Translation $defaultTranslation, CanonicalReference $defaultCanonicalRef) {
        return response()->view(
            "textDisplay.referenceFallback",
            [
                'translation' => $defaultTranslation,
                'requestedTranslation' => $requestedTranslation,
                'canonicalRef' => str_replace(" ", "", $defaultCanonicalRef->toString())
            ],
            404
        );
    }

    /**
     * @param VerseContainer[] $verseContainers 
     */
    private function getBookViewArray($book, array $verseContainers, $translation, $canonicalRef, $translatedRef, $leadVerses = true)
    {
        $chapters = [];
        $groupedVerses = [];

        // Determine heading levels for TOC based on translation config
        $abbrev = $translation->abbrev;
        $headingLevelsRange = Config::get("translations.definitions.{$abbrev}.toc_heading_levels", '5-9');
        if (preg_match('/^(\d)-(\d)$/', $headingLevelsRange, $matches)) {
            $min = $matches[1];
            $max = $matches[2];
            $headingPattern = '/^heading[' . $min . '-' . $max . ']{1}/';
        } else {
            // Fallback to default
            $headingPattern = '/^heading[5-9]{1}/';
        }

        foreach ($verseContainers as $verseContainer) {
            foreach ($verseContainer->rawVerses as $verses) {
                foreach ($verses as $verse) {
                    $type = $verse->getType();
                    if (preg_match($headingPattern, $type)) {
                        $gepi = $verse->gepi;
                        if (!isset($groupedVerses[$gepi])) {
                            $groupedVerses[$gepi] = [];
                        }
                        $groupedVerses[$gepi][] = $verse;
                    }
                }
            }
        }
        $chapterHeadings = [];
        foreach ($groupedVerses as $gepi => $verses) {
            $verseContainer = new VerseContainer($book);
            foreach ($verses as $verse) {
                $verseContainer->addVerse($verse);
            }
            $headings = $this->textService->getHeadings([$verseContainer]);
            if (!empty($headings)) {
                if (!isset($chapterHeadings[$verse->chapter])) {
                    $chapterHeadings[$verse->chapter] = [];
                }
                $chapterHeadings[$verse->chapter] = array_merge($chapterHeadings[$verse->chapter], $headings);
            }
        }
        $leadVerseText = "";
        if ($leadVerses) {
            $firstVerses = $this->verseRepository->getLeadVerses($book->id);

            foreach ($firstVerses as $verse) {
                $type = $verse->getType();
                if ($type == 'text' || $type == 'poemLine') {
                    $verseContainer = new VerseContainer($book);
                    $verseContainer->addVerse($verse);
                    $oldText = "";
                    if (isset($chapters[$verse['chapter']]['leadVerses'])) {
                        if (array_has($chapters[$verse['chapter']]['leadVerses'], $verse['numv'])) {
                            $oldText = $chapters[$verse['chapter']]['leadVerses'][$verse['numv']];
                        }
                    }
                    $verseTeaser = $this->textService->getTeaser([$verseContainer]);
                    $chapters[$verse['chapter']]['leadVerses'][$verse['numv']] = $oldText . $verseTeaser;
                    if (mb_strlen($leadVerseText) < 280 && $verseTeaser !== "") {
                        $leadVerseText = $leadVerseText === "" ? $verseTeaser : "{$leadVerseText} {$verseTeaser}";
                    }
                }
            }
        }
        $allTranslations = $this->translationRepository->getAllOrderedByDenom();
        $bookTranslations = $this->getAllBookTranslations($book->usx_code);
        $bookViewArray = [
            'translation' => $translation,
            'reference' => $translatedRef,
            'book' => $book,
            'teaser' => trim("{$book->name} – {$translation->name}. {$leadVerseText}"),
            'chapters' => $chapters,
            'headings' => $chapterHeadings,
            'translations' => $allTranslations,
            'translationLinks' => $allTranslations->map(
                function ($translation) use ($canonicalRef, $bookTranslations) {
                    $bookExistsInTranslation = $bookTranslations->contains($translation->id);
                    
                    $link = $this->referenceService->getCanonicalUrl($canonicalRef, $translation->id);
                    // Special handling for GNT translation
                    if ($translation->abbrev === 'GNT' && $bookExistsInTranslation) {
                        // For GNT, link to the GreekTextController with the full reference
                        $link = "/GNT/{$canonicalRef->toString()}";
                    }
                    
                    return [
                        'id' => $translation->id,
                        'link' => $link,
                        'abbrev' => $translation->abbrev,
                        'enabled' => $bookExistsInTranslation
                    ];
                }
            )->sortBy(function ($translationLink) {
                // Put GNT at the end
                return $translationLink['abbrev'] === 'GNT' ? 1 : 0;
            })->values()
        ];
        return $bookViewArray;
    }

    /**
     * @param VerseContainer[] $verseContainers
     */
    private function getTitle(array $verseContainers, Translation $translation, ?string $pageHeading): string
    {
        if ($pageHeading !== null) {
            return "{$pageHeading} | {$translation->name}";
        }

        $reference = $this->getReference($verseContainers);

        return $reference !== "" ? "{$reference} – {$translation->name}" : $translation->name;
    }

    /**
     * Builds the descriptive heading used by canonical whole-chapter pages.
     *
     * @param VerseContainer[] $verseContainers
     */
    private function getChapterPageHeading(array $verseContainers): ?string
    {
        if (count($verseContainers) !== 1) {
            return null;
        }

        $verseContainer = $verseContainers[0];
        $chapterRanges = $verseContainer->bookRef?->chapterRanges ?? [];

        if (count($chapterRanges) !== 1) {
            return null;
        }

        $chapterRange = $chapterRanges[0];
        $chapterRef = $chapterRange->chapterRef;

        if (
            $chapterRange->untilChapterRef !== null
            || $chapterRef->chapterPart !== null
            || count($chapterRef->verseRanges) !== 0
        ) {
            return null;
        }

        $isPsalm = $verseContainer->book->usx_code === 'PSA';
        $chapterType = $isPsalm ? 'zsoltár' : 'fejezet';
        $bookName = $isPsalm
            ? (preg_replace('/^A\s+/u', '', $verseContainer->book->name) ?? $verseContainer->book->name)
            : $verseContainer->book->name;

        return "{$bookName} – {$chapterRef->chapterId}. {$chapterType}";
    }

    /**
     * The reference in the canonical abbreviation defined by the translation,
     * e.g. "Mt 23,4". Multiple containers are joined with "; ".
     *
     * @param VerseContainer[] $verseContainers
     */
    private function getReference($verseContainers): string
    {
        $references = [];
        foreach ($verseContainers as $verseContainer) {
            if (isset($verseContainer->bookRef)) {
                $references[] = $verseContainer->bookRef->toString();
            }
        }

        return implode('; ', $references);
    }

    /**
     * Builds the page meta description: the reference and translation name
     * followed by the scripture text, so the description is descriptive,
     * unique and long enough for search engines even for short chapters.
     *
     * @param VerseContainer[] $verseContainers
     */
    private function buildMetaDescription($verseContainers, Translation $translation): string
    {
        $reference = $this->getReference($verseContainers);
        $scripture = $this->textService->getTeaser($verseContainers);
        $prefix = $reference !== "" ? "{$reference} – {$translation->name}." : "{$translation->name}.";

        return trim("{$prefix} {$scripture}");
    }

    /** this only works for one chapter references
     */
    private function createChapterLinks(CanonicalReference $canonicalReference, Translation $translation)
    {
        $currentChapter = $canonicalReference->bookRefs[0]->chapterRanges[0]->chapterRef->chapterId;
        $chapterCount = $this->bookService->getChapterCount($this->bookRepository->getByAbbrevForTranslation($canonicalReference->bookRefs[0]->bookId, $translation), $translation);
        list($prevRef, $nextRef) = $this->referenceService->getPrevNextChapter($canonicalReference, $translation->id);
        $prevLink = $prevRef ?
            $this->referenceService->getCanonicalUrl($prevRef, $translation->id) :
            false;

        $nextLink = $nextRef ?
            $this->referenceService->getCanonicalUrl($nextRef, $translation->id) :
            false;
        return ['prevLink' => $prevLink, 'nextLink' => $nextLink, 'currentChapter' => $currentChapter, 'chapterCount' => $chapterCount];
    }

    /**
     * @param $book
     * @return mixed
     */
    private function getAllBookTranslations($usxCode)
    {
        $translations = $this
            ->translationRepository
            ->getAllOrderedByDenom()
            ->filter(
                function ($translation) use ($usxCode) {
                    // For GNT translation, check if it's a New Testament book
                    if ($translation->abbrev === 'GNT') {
                        // Check if this is a New Testament book
                        // USX codes for NT books are in UsxCodes::NEW_TESTAMENT
                        $isNewTestament = in_array($usxCode, \SzentirasHu\Data\UsxCodes::newTestamentUsx());
                        if (!$isNewTestament) {
                            return false;
                        }
                        // Check if Greek verses exist for this book
                        return Cache::rememberForever("greek_verse_exists_{$usxCode}", function () use ($usxCode) {
                            return \SzentirasHu\Models\GreekVerse::where('usx_code', $usxCode)->exists();
                        });
                    }
                    
                    return $this->bookRepository
                        ->getByUsxCodeForTranslation(
                            $usxCode,
                            $translation
                        );
                }
            );
        return $translations;
    }

    public function showPlaceDetails($placeIds)
    {
        $ids = explode(',', $placeIds);
        $places = Place::whereIn('id', $ids)->withCoordinates()->get();
        
        if ($places->isEmpty()) {
            return response()->json('<p class="text-danger">Hely nem található</p>', 200);
        }

        $html = '<div class="place-details">';
        
        // Create a single map container for all places
        $html .= '<div class="place-map-container mb-3" id="placeMapContainer"></div>';
        
        // Add license attribution
        $html .= '<div class="mt-2 small text-muted">';
        $html .= ' Place data sourced from <a href="https://www.openbible.info/" target="_blank" rel="noopener noreferrer">OpenBible.info</a>';
        $html .= ', licensed under <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">Creative Commons Attribution License</a>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Add data attribute with all place coordinates
        $placesData = [];
        foreach ($places as $place) {
            if ($place->lon_lat) {
                $coords = explode(',', $place->lon_lat);
                if (count($coords) === 2) {
                    $placesData[] = [
                        'lat' => trim($coords[1]),
                        'lon' => trim($coords[0]),
                        'name' => $place->friendly_id,
                    ];
                }
            }
        }
        
        // Append data as JSON in a script tag for the JavaScript to access
        $html .= '<script type="application/json" id="placeMapData">' . json_encode($placesData) . '</script>';
        
        return response()->json($html);
    }
}
