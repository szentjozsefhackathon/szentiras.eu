<?php

namespace SzentirasHu\Service\FamousPassage;

use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePartType;
use SzentirasHu\Service\VerseContainer;
use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\NumberingSchemeService;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

/**
 * Resolves a famous passage's reference (written in the default translation's notation)
 * to its text in every translation.
 */
class FamousPassageService
{
    public function __construct(
        protected TranslationService $translationService,
        protected TranslationRepository $translationRepository,
        protected BookRepository $bookRepository,
        protected ReferenceService $referenceService,
        protected TextService $textService,
        protected NumberingSchemeService $numberingSchemeService,
    ) {}

    /**
     * The book and chapters a reference covers in the default translation, or null when the
     * reference is not a single-book reference with text behind it.
     *
     * @return array{usx_code: string, chapter_from: int, chapter_to: int}|null
     */
    public function locate(string $reference): ?array
    {
        $translation = $this->translationService->getDefaultTranslation();
        $canonicalReference = $this->parse($reference, $translation);
        if ($canonicalReference === null || count($canonicalReference->bookRefs) !== 1) {
            return null;
        }

        $bookRef = $canonicalReference->bookRefs[0];
        $book = $this->bookRepository->getByAbbrevForTranslation($bookRef->bookId, $translation);
        if ($book === null || count($bookRef->chapterRanges) === 0 || ! $this->hasText($canonicalReference, $translation)) {
            return null;
        }

        $chapters = [];
        foreach ($bookRef->chapterRanges as $chapterRange) {
            $chapters[] = (int) $chapterRange->chapterRef->chapterId;
            if ($chapterRange->untilChapterRef !== null) {
                $chapters[] = (int) $chapterRange->untilChapterRef->chapterId;
            }
        }

        return [
            'usx_code' => $book->usx_code,
            'chapter_from' => min($chapters),
            'chapter_to' => max($chapters),
        ];
    }

    /**
     * @return VerseContainer[]
     */
    public function verses(FamousPassage $passage, Translation $translation): array
    {
        $reference = $this->referenceIn($passage->reference, $translation);

        return $reference === null ? [] : $this->textService->getTranslatedVerses($reference, $translation);
    }

    /**
     * Every translation that has the passage's book: a link to the passage in it, and the key
     * verse's text when the passage has one.
     *
     * @return list<array{translation: Translation, url: string, keyVerseText: string|null}>
     */
    public function translationRows(FamousPassage $passage): array
    {
        return $this->translationRepository->getAllOrderedByDenom()
            ->filter(fn (Translation $translation): bool => $translation->abbrev !== 'GNT'
                && $this->bookRepository->getByUsxCodeForTranslation($passage->usx_code, $translation) !== null)
            ->map(function (Translation $translation) use ($passage): array {
                $passageReference = $this->referenceIn($passage->reference, $translation);
                $keyVerseReference = $passage->key_verse === null ? null : $this->referenceIn($passage->key_verse, $translation);
                $keyVerseText = $keyVerseReference === null ? null : $this->plainText($keyVerseReference, $translation);

                return [
                    'translation' => $translation,
                    'url' => $this->url($passage, $passageReference, $translation),
                    'keyVerseText' => $keyVerseText === '' ? null : $keyVerseText,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The reference, written in the default translation's notation and numbering, in the given
     * translation's book names and numbering (KAL numbers the Psalms the Vulgate way).
     */
    private function referenceIn(string $reference, Translation $translation): ?CanonicalReference
    {
        $defaultReference = $this->parse($reference, $this->translationService->getDefaultTranslation());
        if ($defaultReference === null) {
            return null;
        }
        $translatedReference = $this->referenceService->translateReference($defaultReference, $translation->id);

        return $this->numberingSchemeService->usesVulgataNumbering($translation->abbrev)
            ? $this->numberingSchemeService->toVulgata($translatedReference)
            : $translatedReference;
    }

    /**
     * The passage in the translation: its chapter page when the passage is a whole chapter, so
     * the link leads to an indexed page even where the numbering differs ("/KAL/Zsolt22").
     */
    private function url(FamousPassage $passage, ?CanonicalReference $passageReference, Translation $translation): string
    {
        $defaultReference = $this->parse($passage->reference, $this->translationService->getDefaultTranslation());
        $bookRef = $passageReference?->bookRefs[0] ?? null;
        if ($defaultReference?->isOneChapter() && $bookRef !== null && count($bookRef->chapterRanges) === 1) {
            return "/{$translation->abbrev}/{$bookRef->bookId}{$bookRef->chapterRanges[0]->chapterRef->chapterId}";
        }

        return '/'.$translation->abbrev.'/'.preg_replace('/\s+/', '', (string) $passageReference?->toString());
    }

    /**
     * The verses' text without headings, footnotes and markup. Verse parts are joined with a
     * space, so a superscription and a poem line ("Dávid zsoltára." "Az Úr…") stay apart.
     */
    private function plainText(CanonicalReference $reference, Translation $translation): string
    {
        $parts = [];
        foreach ($this->textService->getTranslatedVerses($reference, $translation) as $verseContainer) {
            foreach ($verseContainer->getParsedVerses() as $verse) {
                foreach ($verse->verseParts as $versePart) {
                    if ($versePart->type !== VersePartType::HEADING) {
                        $parts[] = strip_tags(str_replace('<', ' <', $versePart->content));
                    }
                }
            }
        }

        return trim((string) preg_replace(['/\s+/u', '/\s+([,.;:!?])/u'], [' ', '$1'], implode(' ', $parts)));
    }

    private function parse(string $reference, Translation $translation): ?CanonicalReference
    {
        try {
            return CanonicalReference::fromString($reference, $translation->id);
        } catch (ParsingException) {
            return null;
        }
    }

    private function hasText(CanonicalReference $reference, Translation $translation): bool
    {
        foreach ($this->textService->getTranslatedVerses($reference, $translation) as $verseContainer) {
            if (! empty($verseContainer->rawVerses)) {
                return true;
            }
        }

        return false;
    }
}
