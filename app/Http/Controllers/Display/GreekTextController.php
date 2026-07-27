<?php

namespace SzentirasHu\Http\Controllers\Display;

use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ChapterRange;
use SzentirasHu\Service\Reference\NumberingSchemeService;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

class GreekTextController extends Controller
{
    public function __construct(
        protected BookService $bookService,
        protected TranslationService $translationService,
        protected TranslationRepository $translationRepository,
        protected ReferenceService $referenceService,
        protected NumberingSchemeService $numberingSchemeService,
        protected TextService $textService
    ) {}

    public function show(?string $reference = null)
    {
        $templateTranslation = 7;
        $books = $this->bookService->getBooksForTranslation($this->translationService->getById($templateTranslation));
        $translation = new Translation;
        $translation->abbrev = 'GNT';
        $translation->id = $templateTranslation; // Set ID for consistency

        $book = null;
        $greekVerses = collect();
        $canonicalRef = null;
        $chapters = collect();
        $currentChapter = null;
        $previousChapter = null;
        $nextChapter = null;
        $bookRef = null;
        $highlightedGreekVerseGepis = [];
        $scrollTo = null;

        if ($reference) {
            $requestedChapter = null;
            try {
                $canonicalRef = CanonicalReference::fromString($reference, $templateTranslation);
                $scheme = request()->query('scheme', 'default');
                if ($scheme === 'vulgata') {
                    $canonicalRef = $this->numberingSchemeService->convertReference($canonicalRef, 'vulgata', 'default');
                }

                // Get the first book reference (GNT references should only have one book)
                if (count($canonicalRef->bookRefs) > 0) {
                    $bookRef = $canonicalRef->bookRefs[0];
                    $book = collect($books)->firstWhere('abbrev', $bookRef->bookId);

                    if ($book && count($bookRef->chapterRanges) > 0) {
                        $requestedChapter = $bookRef->chapterRanges[0]->chapterRef->chapterId;
                    }
                }
            } catch (ParsingException $e) {
                // If parsing fails, fall back to treating it as a book abbreviation
                $book = collect($books)->firstWhere('abbrev', $reference);
            }

            if ($book) {
                $chapters = GreekVerse::where('usx_code', $book->usx_code)
                    ->distinct()
                    ->orderBy('chapter')
                    ->pluck('chapter');

                // Reading pages always show a single chapter; default to the first one.
                $currentChapter = $chapters->contains($requestedChapter) ? $requestedChapter : $chapters->first();

                if ($currentChapter !== null) {
                    $greekVerses = GreekVerse::where('usx_code', $book->usx_code)
                        ->where('chapter', $currentChapter)
                        ->withVerseAnalysisAvailability()
                        ->orderBy('verse')
                        ->get();

                    $position = $chapters->search($currentChapter);
                    $previousChapter = $position > 0 ? $chapters[$position - 1] : null;
                    $nextChapter = $position < $chapters->count() - 1 ? $chapters[$position + 1] : null;
                }
            }
        }

        $hasExplicitVerseSelection = $bookRef !== null
            && collect($bookRef->chapterRanges)->contains(
                static fn (ChapterRange $chapterRange): bool => count($chapterRange->chapterRef->verseRanges) > 0
                    || ($chapterRange->untilChapterRef !== null && count($chapterRange->untilChapterRef->verseRanges) > 0)
            );

        if ($hasExplicitVerseSelection) {
            $highlightedGreekVerses = $greekVerses->filter(
                static function (GreekVerse $greekVerse) use ($bookRef): bool {
                    foreach ($bookRef->chapterRanges as $chapterRange) {
                        $rangeHasExplicitVerseSelection = count($chapterRange->chapterRef->verseRanges) > 0
                            || ($chapterRange->untilChapterRef !== null && count($chapterRange->untilChapterRef->verseRanges) > 0);

                        if ($rangeHasExplicitVerseSelection && $chapterRange->hasVerse($greekVerse->chapter, $greekVerse->verse)) {
                            return true;
                        }
                    }

                    return false;
                }
            );

            $highlightedGreekVerseGepis = $highlightedGreekVerses->pluck('gepi')->all();
            $firstHighlightedGreekVerse = $highlightedGreekVerses->first();
            if ($firstHighlightedGreekVerse !== null) {
                $scrollTo = "{$firstHighlightedGreekVerse->usx_code}_{$firstHighlightedGreekVerse->chapter}_{$firstHighlightedGreekVerse->verse}";
            }
        }

        // Handle parallel comparison translation (read a translation next to the Greek text)
        $compareTranslation = null;
        $compareVerseContainers = null;
        $compareAbbrev = request()->query('compare');
        if ($compareAbbrev && $compareAbbrev !== 'GNT' && $book && $currentChapter !== null) {
            $candidate = $this->translationRepository->getByAbbrev($compareAbbrev);
            if ($this->translationRepository->getAll()->contains($candidate)) {
                try {
                    $chapterRef = CanonicalReference::fromString("{$book->abbrev}{$currentChapter}", $templateTranslation);
                    $compareRef = $this->referenceService->translateReference($chapterRef, $candidate->id);
                    $containers = $this->textService->getTranslatedVerses($compareRef, $candidate);
                    if (! empty($containers)) {
                        $compareTranslation = $candidate;
                        $compareVerseContainers = $containers;
                    }
                } catch (ParsingException $e) {
                    // Reference not available in the comparison translation; skip comparison silently.
                }
            }
        }

        // Get all translations for the translation switcher
        $allTranslations = $this->translationRepository->getAll();

        // Generate translation links
        $translationLinks = $allTranslations->map(function ($otherTranslation) use ($canonicalRef, $reference) {
            // For GNT to other translations, we need to check if the book exists in the other translation
            $enabled = true;
            $link = '';

            if ($otherTranslation->abbrev === 'GNT') {
                // Switching to GNT from GNT - stay on same page
                $link = $reference ? "/GNT/{$reference}" : '/GNT';
                $enabled = true;
            } else {
                // Switching to other translation from GNT
                if ($canonicalRef && count($canonicalRef->bookRefs) > 0) {
                    // Try to get the canonical URL for the other translation
                    try {
                        $link = $this->referenceService->getCanonicalUrl($canonicalRef, $otherTranslation->id);
                        $enabled = true;
                    } catch (\Exception $e) {
                        $enabled = false;
                        $link = '';
                    }
                } elseif ($reference) {
                    // Simple book reference - try to convert
                    $link = "/{$otherTranslation->abbrev}/{$reference}";
                    $enabled = true;
                } else {
                    // No reference - just go to translation home
                    $link = "/{$otherTranslation->abbrev}";
                    $enabled = true;
                }
            }

            return [
                'id' => $otherTranslation->id,
                'link' => $link,
                'abbrev' => $otherTranslation->abbrev,
                'enabled' => $enabled,
            ];
        })->sortBy(function ($translationLink) {
            // Put GNT at the end
            return $translationLink['abbrev'] === 'GNT' ? 1 : 0;
        })->values();

        if ($book && $currentChapter !== null) {
            $teaser = "{$book->name}, {$currentChapter}. fejezet görög szövege – Görög Újszövetség";
        } elseif ($book) {
            $teaser = "{$book->name} görög szövege az Újszövetségből – Görög Újszövetség";
        } else {
            $teaser = 'Teljes görög Újszövetség és görög–magyar szószedet, görög újszövetségi szentírás, újszövetségi szótár';
        }

        $gntReferencePath = $book && $currentChapter !== null
            ? "/GNT/{$book->abbrev}{$currentChapter}"
            : '/GNT';
        if ($hasExplicitVerseSelection && $canonicalRef !== null) {
            $gntReferencePath = '/GNT/'.str_replace(' ', '%20', $canonicalRef->toString());
        }

        return view('greekText.gnt', [
            'translation' => $translation,
            'books' => $books,
            'greekVerses' => $greekVerses,
            'book' => $book,
            'chapters' => $chapters,
            'currentChapter' => $currentChapter,
            'previousChapter' => $previousChapter,
            'nextChapter' => $nextChapter,
            'translationLinks' => $translationLinks,
            'showLanding' => $book === null,
            'teaser' => $teaser,
            'compareTranslation' => $compareTranslation,
            'compareVerseContainers' => $compareVerseContainers,
            'highlightedGreekVerseGepis' => $highlightedGreekVerseGepis,
            'scrollTo' => $scrollTo,
            'gntReferencePath' => $gntReferencePath,
        ]);
    }
}
