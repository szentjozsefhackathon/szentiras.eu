<?php

namespace SzentirasHu\Http\Controllers\Display;

use Cache;
use Config;
use Illuminate\Support\Facades\Log;
use Redirect;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Reference\CanonicalReference;
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
use SzentirasHu\Models\Media;
use SzentirasHu\Service\Text\BookService;
use View;


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

    function __construct(TranslationRepository $translationRepository, BookRepository $bookRepository, VerseRepository $verseRepository, ReadingPlanRepository $readingPlanRepository, ReferenceService $referenceService, TextService $textService, protected BookService $bookService)
    {
        $this->translationRepository = $translationRepository;
        $this->bookRepository = $bookRepository;
        $this->verseRepository = $verseRepository;
        $this->readingPlanRepository = $readingPlanRepository;
        $this->referenceService = $referenceService;
        $this->textService = $textService;
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
        $translation = $this->translationRepository->getByAbbrev($translationAbbrev);
        $books = $this->translationRepository->getBooks($translation);
        $bookHeaders = [];
        $toc = request()->has("toc");
        if ($toc) {
            $bookHeaders = Cache::remember("bookHeaders_{$translationAbbrev}", 60 * 24, function () use ($books, $translation) {
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
                'toc' => $toc
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

    public function showTranslatedReferenceText($translationAbbrev, $reference, $previousDay = null, $readingPlanDay = null, $nextDay = null)
    {
        try {
            $translation = $this->translationRepository->getByAbbrev($translationAbbrev ? $translationAbbrev : Config::get('settings.defaultTranslationAbbrev'));
            $canonicalRef = CanonicalReference::fromString($reference, $translation->id);
            if ($canonicalRef->isBookLevel()) {
                return $this->bookView($translationAbbrev, $canonicalRef);
            }
            $chapterLinks = $canonicalRef->isOneChapter() ?
                $this->createChapterLinks($canonicalRef, $translation)
                : false;
            $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation);
            if (sizeof($verseContainers) == 1 && empty($verseContainers[0]->rawVerses)) {
                abort(404);
            }
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
                $chapterReferenceString = '';
                foreach ($chapterNumbers as $bookId => $chapters) {
                    // ["Mt" => [1,2], "Mk" => [2,3]] should be "Mt1;2;3;Mk3"
                    $chapterReferenceString .= $bookId;
                    $chapterReferenceString .= implode(';', $chapters);
                }
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
                    $bookNumber = explode("_", $book_chapter)[0];
                    $book = $this->bookService->getBookByUsxCodeTranslation($bookNumber, $translation->abbrev);
                    $chapterNumber = explode("_", $book_chapter)[1];
                    $verseMedia = [];
                    foreach ($mediaItems as $mediaItem) {
                        $verseMedia[$mediaItem->verse] = $mediaItem;
                    }
                    // now we have media items for all verses in the chapter
                    foreach ($verseMedia as $verse => $mediaItems) {
                        $chapterLength = $this->bookService->getVerseCount($book, $chapterNumber, $translation);
                        if ($verse <= $chapterLength) {
                            $mediaVerses["{$bookNumber}_{$chapterNumber}_{$verse}"][] = $mediaItems;
                        } else {
                            $otherMedia["{$bookNumber}_{$chapterNumber}"][] = $mediaItems;
                        }
                    }
                }
            }

            $translations = $this->translationRepository->getAllOrderedByDenom();
            return View::make('textDisplay.verses')->with([
                'fullChaptersIncluded' => $fullChaptersIncluded,
                'highlightedGepis' => $highlightedGepis ?? [],
                'fullContext' => $fullContext,
                'mediaEnabled' => $mediaEnabled,
                'hasMedia' => $hasMedia,
                'previousDay' => $previousDay,
                'readingPlan' => $readingPlanDay ? $readingPlanDay->plan : null,
                'readingPlanDay' => $readingPlanDay,
                'nextDay' => $nextDay,
                'canonicalRef' => str_replace(" ", "%20", $canonicalRef->toString()),
                'verseContainers' => $fullContextVerseContainers ?? $verseContainers,
                'translation' => $translation,
                'translations' => $translations,
                'canonicalUrl' => $this->referenceService->getCanonicalUrl($canonicalRef, $translation->id),
                'seoUrl' => $this->referenceService->getSeoUrl($canonicalRef, $translation->id),
                'metaTitle' => $this->getTitle($verseContainers, $translation),
                'teaser' => $this->textService->getTeaser($verseContainers),
                'chapterLinks' => $chapterLinks,
                'media' => $mediaVerses ?? [],
                'otherMedia' => $otherMedia ?? [],
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
                        return [
                            'id' => $otherTranslation->id,
                            'link' => $this->referenceService->getCanonicalUrl($canonicalRef, $otherTranslation->id, $translation->id),
                            'abbrev' => $otherTranslation->abbrev,
                            'enabled' => $allBooksExistInTranslation
                        ];
                    }
                )
            ]);
        } catch (ParsingException $e) {
            // as this doesn't look like a valid reference
            abort(404);
        }
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
            abort(404);
        }
    }

    /**
     * @param VerseContainer[] $verseContainers 
     */
    private function getBookViewArray($book, array $verseContainers, $translation, $canonicalRef, $translatedRef, $leadVerses = true)
    {
        $chapters = [];
        $groupedVerses = [];
        foreach ($verseContainers as $verseContainer) {
            foreach ($verseContainer->rawVerses as $verses) {
                foreach ($verses as $verse) {
                    $type = $verse->getType();
                    if (preg_match('/^heading[5-9]{1}/', $type)) {
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
                    $chapters[$verse['chapter']]['leadVerses'][$verse['numv']] = $oldText . $this->textService->getTeaser([$verseContainer]);
                }
            }
        }
        $allTranslations = $this->translationRepository->getAllOrderedByDenom();
        $bookTranslations = $this->getAllBookTranslations($book->usx_code);
        $bookViewArray = [
            'translation' => $translation,
            'reference' => $translatedRef,
            'book' => $book,
            'chapters' => $chapters,
            'headings' => $chapterHeadings,
            'translations' => $allTranslations,
            'translationLinks' => $allTranslations->map(
                function ($translation) use ($canonicalRef, $bookTranslations) {
                    $bookExistsInTranslation = $bookTranslations->contains($translation->id);
                    return [
                        'id' => $translation->id,
                        'link' => $this->referenceService->getCanonicalUrl($canonicalRef, $translation->id),
                        'abbrev' => $translation->abbrev,
                        'enabled' => $bookExistsInTranslation
                    ];
                }
            )
        ];
        return $bookViewArray;
    }

    private function getTitle($verseContainers, $translation)
    {
        $title = "";
        $title .= "{$translation->name}";
        foreach ($verseContainers as $verseContainer) {
            if (isset($verseContainer->book)) {
                $title .= " - {$verseContainer->book->name}";
            }
            if (isset($verseContainer->bookRef)) {
                $title .= " - {$verseContainer->bookRef->toString()}";
            }
        }
        return $title;
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
                    return $this->bookRepository
                        ->getByUsxCodeForTranslation(
                            $usxCode,
                            $translation
                        );
                }
            );
        return $translations;
    }
}
