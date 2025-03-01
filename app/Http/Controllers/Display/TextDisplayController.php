<?php

namespace SzentirasHu\Http\Controllers\Display;

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
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Data\Repository\VerseRepository;
use SzentirasHu\Data\Repository\ReadingPlanRepository;
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

    function __construct(TranslationRepository $translationRepository, BookRepository $bookRepository, VerseRepository $verseRepository, ReadingPlanRepository $readingPlanRepository, ReferenceService $referenceService, TextService $textService)
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
        return View::make('textDisplay.translation',
            ['translation' => $translation,
                'books' => $books]);
    }

    public function showReferenceText($reference)
    {
        return $this->showTranslatedReferenceText(null, $reference);
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
            $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation->id);
            if (sizeof($verseContainers) == 1 && empty($verseContainers[0]->rawVerses)) {
                abort(404);
            }
            $translations = $this->translationRepository->getAllOrderedByDenom();
            return View::make('textDisplay.verses')->with([
                'previousDay' => $previousDay,
                'readingPlan' => $readingPlanDay ? $readingPlanDay->plan : null,
                'readingPlanDay' => $readingPlanDay,
                'nextDay' => $nextDay,
				'canonicalRef' => str_replace(" ", "%20", $canonicalRef->toString()),
                'verseContainers' => $verseContainers,
                'translation' => $translation,
                'translations' => $translations,
                'canonicalUrl' => $this->referenceService->getCanonicalUrl($canonicalRef, $translation->id),
                'metaTitle' => $this->getTitle($verseContainers, $translation),
                'teaser' => $this->textService->getTeaser($verseContainers),
                'chapterLinks' => $chapterLinks,
                'translationLinks' => $translations->map(
                        function ($otherTranslation) use ($canonicalRef, $translation) {
                            $allBooksExistInTranslation = true;
                            foreach ($canonicalRef->bookRefs as $bookRef) {
                                $book = $this->bookRepository->getByAbbrevForTranslation($bookRef->bookId, $translation->id);
                                if (!$this->getAllBookTranslations($book->number)->contains($otherTranslation->id)) {
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
        $book = $this->bookRepository->getByAbbrevForTranslation($translatedRef->bookRefs[0]->bookId, $translation->id);
        if ($book) {           
            $chapters = [];                    
            $verses = $this->verseRepository->getVerses($book->id);
            $groupedVerses = [];
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
            $firstVerses = $this->verseRepository->getLeadVerses($book->id);

            foreach ($firstVerses as $verse) {
                $type = $verse->getType();
                if ($type == 'text' || $type == 'poemLine') {
                    $verseContainer = new VerseContainer($book);
                    $verseContainer->addVerse($verse);
                    $oldText = "";                    
                    if(isset($chapters[$verse['chapter']]['leadVerses'])) {                   
                        if (array_has($chapters[$verse['chapter']]['leadVerses'], $verse['numv'])) {
                            $oldText = $chapters[$verse['chapter']]['leadVerses'][$verse['numv']];
                        }
                    }
                    $chapters[$verse['chapter']]['leadVerses'][$verse['numv']] = $oldText . $this->textService->getTeaser([$verseContainer]);
                }
            }            
            $allTranslations = $this->translationRepository->getAllOrderedByDenom();
            $bookTranslations = $this->getAllBookTranslations($book->number);
            return View::make('textDisplay.book', [
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
                                'enabled' => $bookExistsInTranslation];
                        }
                    )

            ]);

        } else {
            abort(404);
        }

    }

    private function fallbackSearch($translation, $reference)
    {
        $location = "/kereses/search?textToSearch={$reference}";
        if ($translation) {
            $location .= "&translation={$translation->id}";
        }
        return Redirect::to($location);
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

    private function createChapterLinks(CanonicalReference $canonicalReference, Translation $translation)
    {
        list($prevRef, $nextRef) = $this->referenceService->getPrevNextChapter($canonicalReference, $translation->id);
        $prevLink = $prevRef ?
            $this->referenceService->getCanonicalUrl($prevRef, $translation->id) :
            false;

        $nextLink = $nextRef ?
            $this->referenceService->getCanonicalUrl($nextRef, $translation->id) :
            false;
        return ['prevLink' => $prevLink, 'nextLink' => $nextLink];
    }

    /**
     * @param $book
     * @return mixed
     */
    private function getAllBookTranslations($bookOrder)
    {
        $translations = $this->translationRepository->getAllOrderedByDenom()->filter(function ($translation) use ($bookOrder) {
                return $this->bookRepository->getByOrderForTranslation($bookOrder, $translation->id);
            }
        );
        return $translations;
    }
}
