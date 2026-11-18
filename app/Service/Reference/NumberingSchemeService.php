<?php

namespace SzentirasHu\Service\Reference;

use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Data\UsxCodes;
use SzentirasHu\Service\Text\TextService;

class NumberingSchemeService
{
    private TranslationRepository $translationRepository;
    private TextService $textService;

    /** @var array|null */
    private static $mapping = null;

    /** @var array|null */
    private static $chapterMapping = null;

    public function __construct(TranslationRepository $translationRepository, TextService $textService)
    {
        $this->translationRepository = $translationRepository;
        $this->textService = $textService;
    }

    /**
     * Convert a reference from one numbering scheme to another.
     * Currently only supports 'vulgata' -> 'default'.
     *
     * @param CanonicalReference $ref
     * @param string $fromScheme
     * @param string $toScheme
     * @return CanonicalReference
     */
    public function convertReference(CanonicalReference $ref, string $fromScheme, string $toScheme = 'default'): CanonicalReference
    {
        if ($fromScheme !== 'vulgata' || $toScheme !== 'default') {
            // No conversion needed (or unknown scheme)
            return $ref;
        }

        $translation = $ref->translationId
            ? $this->translationRepository->getById($ref->translationId)
            : null;
        $translationAbbrev = $translation ? $translation->abbrev : 'default';

        // If reference lacks verse numbers, try chapter mapping first
        if (!$this->hasVerseNumbers($ref)) {
            $mappedVerses = $this->expandChapterUsingMapping($ref, $translationAbbrev);
            if (!empty($mappedVerses)) {
                return $this->buildCanonicalReference($mappedVerses, $ref->translationId);
            }
            // If no chapter mapping found, fall back to verse expansion
        }

        $verseList = $this->expandToVerseList($ref);
        if (empty($verseList)) {
            return $ref;
        }
        $mappedVerses = [];

        foreach ($verseList as $verse) {
            $usx = UsxCodes::getUsxFromBookAbbrevAndTranslation($verse['bookId'], $translationAbbrev);
            if (!$usx) {
                // If we cannot map the book, keep the verse as is
                $mappedVerses[] = $verse;
                continue;
            }

            $key = "{$usx} {$verse['chapter']}:{$verse['verse']}";
            $mapping = $this->getMapping();
            if (isset($mapping[$key])) {
                [$targetUsx, $targetChapter, $targetVerse] = $mapping[$key];
                $targetBookId = UsxCodes::getPreferredAbbreviation($targetUsx, $translationAbbrev) ?? $targetUsx;
                $mappedVerses[] = [
                    'bookId' => $targetBookId,
                    'chapter' => $targetChapter,
                    'verse' => $targetVerse,
                ];
            } else {
                $mappedVerses[] = $verse;
            }
        }

        return $this->buildCanonicalReference($mappedVerses, $ref->translationId);
    }

    /**
     * Whether a translation numbers its chapters and verses the Vulgate way (KAL), set by
     * 'numberingScheme' => 'vulgata' in its translation definition.
     */
    public function usesVulgataNumbering(string $translationAbbrev): bool
    {
        return config("translations.definitions.{$translationAbbrev}.numberingScheme") === 'vulgata';
    }

    /**
     * Converts a reference written in the default numbering into the Vulgate numbering:
     * "Zsolt 23" becomes "Zsolt 22,1-6". Chapters and verses without a mapping keep their numbers.
     */
    public function toVulgata(CanonicalReference $ref): CanonicalReference
    {
        $translation = $ref->translationId ? $this->translationRepository->getById($ref->translationId) : null;
        $translationAbbrev = $translation ? $translation->abbrev : 'default';
        $vulgataVersesByDefaultVerse = $this->getVulgataVersesByDefaultVerse();

        $verses = $this->hasVerseNumbers($ref) ? $this->expandToVerseList($ref) : [];
        if ($verses === []) {
            foreach ($ref->bookRefs as $bookRef) {
                $usx = UsxCodes::getUsxFromBookAbbrevAndTranslation($bookRef->bookId, $translationAbbrev);
                foreach ($bookRef->chapterRanges as $chapterRange) {
                    $endChapter = $chapterRange->untilChapterRef->chapterId ?? $chapterRange->chapterRef->chapterId;
                    for ($chapter = $chapterRange->chapterRef->chapterId; $chapter <= $endChapter; $chapter++) {
                        $mappedVerses = array_filter(
                            $vulgataVersesByDefaultVerse,
                            fn (string $key): bool => str_starts_with($key, "{$usx} {$chapter}:"),
                            ARRAY_FILTER_USE_KEY,
                        );
                        if ($mappedVerses === []) {
                            return $ref;
                        }
                        foreach ($mappedVerses as [$targetUsx, $targetChapter, $targetVerse]) {
                            $verses[] = [
                                'bookId' => UsxCodes::getPreferredAbbreviation($targetUsx, $translationAbbrev) ?? $bookRef->bookId,
                                'chapter' => $targetChapter,
                                'verse' => $targetVerse,
                            ];
                        }
                    }
                }
            }

            return $this->buildCanonicalReference($verses, $ref->translationId);
        }

        $mappedVerses = [];
        foreach ($verses as $verse) {
            $usx = UsxCodes::getUsxFromBookAbbrevAndTranslation($verse['bookId'], $translationAbbrev);
            $key = "{$usx} {$verse['chapter']}:{$verse['verse']}";
            if ($usx && isset($vulgataVersesByDefaultVerse[$key])) {
                [$targetUsx, $targetChapter, $targetVerse] = $vulgataVersesByDefaultVerse[$key];
                $mappedVerses[] = [
                    'bookId' => UsxCodes::getPreferredAbbreviation($targetUsx, $translationAbbrev) ?? $verse['bookId'],
                    'chapter' => $targetChapter,
                    'verse' => $targetVerse,
                ];
            } else {
                $mappedVerses[] = $verse;
            }
        }

        return $this->buildCanonicalReference($mappedVerses, $ref->translationId);
    }

    /**
     * The default-numbered chapter a Vulgate-numbered chapter starts in: Psalm 22 → 23.
     */
    public function defaultChapterOf(string $usxCode, int $vulgataChapter): int
    {
        return $this->getChapterMapping()["{$usxCode} {$vulgataChapter}"][0][1] ?? $vulgataChapter;
    }

    /**
     * The verse mapping inverted: "PSA 23:1" (default) => ['PSA', 22, 1] (Vulgate).
     *
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    private function getVulgataVersesByDefaultVerse(): array
    {
        $inverted = [];
        foreach ($this->getMapping() as $vulgataKey => [$usx, $chapter, $verse]) {
            if (preg_match('/^(\S+) (\d+):(\d+)$/', $vulgataKey, $matches)) {
                $inverted["{$usx} {$chapter}:{$verse}"] ??= [$matches[1], (int) $matches[2], (int) $matches[3]];
            }
        }
        uasort($inverted, fn (array $a, array $b): int => [$a[1], $a[2]] <=> [$b[1], $b[2]]);

        return $inverted;
    }

    /**
     * Expand a CanonicalReference into a flat list of individual verses.
     * Each verse is represented as ['bookId' => ..., 'chapter' => ..., 'verse' => ...].
     *
     * @param CanonicalReference $ref
     * @return array
     */
    private function expandToVerseList(CanonicalReference $ref): array
    {
        $verses = [];
        foreach ($ref->bookRefs as $bookRef) {
            foreach ($bookRef->chapterRanges as $chapterRange) {
                $startChapter = $chapterRange->chapterRef->chapterId;
                $endChapter = $chapterRange->untilChapterRef !== null
                    ? $chapterRange->untilChapterRef->chapterId
                    : $startChapter;
                for ($chapter = $startChapter; $chapter <= $endChapter; $chapter++) {
                    $verseRanges = $this->getVerseRangesForChapter($chapterRange, $chapter);
                    if (empty($verseRanges)) {
                        // No verse ranges means whole chapter; we cannot expand without knowing verse count.
                        // Return empty to fallback to chapter mapping.
                        return [];
                    }
                    foreach ($verseRanges as $verseRange) {
                        $startVerse = $verseRange->verseRef !== null ? $verseRange->verseRef->verseId : 1;
                        $endVerse = $verseRange->untilVerseRef !== null ? $verseRange->untilVerseRef->verseId : $startVerse;
                        for ($verse = $startVerse; $verse <= $endVerse; $verse++) {
                            $verses[] = [
                                'bookId' => $bookRef->bookId,
                                'chapter' => $chapter,
                                'verse' => $verse,
                            ];
                        }
                    }
                }
            }
        }
        return $verses;
    }

    /**
     * Get verse ranges for a specific chapter within a chapter range.
     *
     * @param ChapterRange $chapterRange
     * @param int $chapter
     * @return VerseRange[]
     */
    private function getVerseRangesForChapter(ChapterRange $chapterRange, int $chapter): array
    {
        if ($chapter === $chapterRange->chapterRef->chapterId) {
            return $chapterRange->chapterRef->verseRanges;
        }
        if ($chapterRange->untilChapterRef !== null && $chapter === $chapterRange->untilChapterRef->chapterId) {
            return $chapterRange->untilChapterRef->verseRanges;
        }
        return [];
    }

    /**
     * Determine if a reference includes verse numbers.
     *
     * @param CanonicalReference $ref
     * @return bool
     */
    private function hasVerseNumbers(CanonicalReference $ref): bool
    {
        foreach ($ref->bookRefs as $bookRef) {
            foreach ($bookRef->chapterRanges as $chapterRange) {
                if (!empty($chapterRange->chapterRef->verseRanges)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Expand a chapter-only reference using chapter mapping.
     * Returns an array of mapped verses, or empty array if no mapping found.
     *
     * @param CanonicalReference $ref
     * @param string $translationAbbrev
     * @return array
     */
    private function expandChapterUsingMapping(CanonicalReference $ref, string $translationAbbrev): array
    {
        $mappedVerses = [];
        foreach ($ref->bookRefs as $bookRef) {
            $usx = UsxCodes::getUsxFromBookAbbrevAndTranslation($bookRef->bookId, $translationAbbrev);
            if (!$usx) {
                continue;
            }
            foreach ($bookRef->chapterRanges as $chapterRange) {
                $startChapter = $chapterRange->chapterRef->chapterId;
                $endChapter = $chapterRange->untilChapterRef ? $chapterRange->untilChapterRef->chapterId : $startChapter;
                for ($chapter = $startChapter; $chapter <= $endChapter; $chapter++) {
                    $key = "{$usx} {$chapter}";
                    $chapterMapping = $this->getChapterMapping();
                    if (isset($chapterMapping[$key])) {
                        foreach ($chapterMapping[$key] as [$targetUsx, $targetChapter, $targetVerse]) {
                            $targetBookId = UsxCodes::getPreferredAbbreviation($targetUsx, $translationAbbrev) ?? $targetUsx;
                            $mappedVerses[] = [
                                'bookId' => $targetBookId,
                                'chapter' => $targetChapter,
                                'verse' => $targetVerse,
                            ];
                        }
                    } else {
                        // No mapping for this chapter, return empty to fallback
                        return [];
                    }
                }
            }
        }
        return $mappedVerses;
    }


    /**
     * Build a CanonicalReference from a list of mapped verses.
     * Verses are assumed to be already grouped and sorted by book, chapter, verse.
     *
     * @param array $verses
     * @param int|null $translationId
     * @return CanonicalReference
     */
    private function buildCanonicalReference(array $verses, ?int $translationId): CanonicalReference
    {
        // Group by bookId, then chapter, then collect verse numbers
        $grouped = [];
        foreach ($verses as $verse) {
            $bookId = $verse['bookId'];
            $chapter = $verse['chapter'];
            $verseNum = $verse['verse'];
            if (!isset($grouped[$bookId])) {
                $grouped[$bookId] = [];
            }
            if (!isset($grouped[$bookId][$chapter])) {
                $grouped[$bookId][$chapter] = [];
            }
            $grouped[$bookId][$chapter][] = $verseNum;
        }

        $bookRefs = [];
        foreach ($grouped as $bookId => $chapters) {
            $bookRef = new BookRef($bookId);
            ksort($chapters);
            foreach ($chapters as $chapter => $verseList) {
                sort($verseList);
                $chapterRef = new ChapterRef($chapter);
                // Merge contiguous verses into ranges
                $ranges = $this->mergeVersesToRanges($verseList);
                foreach ($ranges as [$start, $end]) {
                    $verseRange = new VerseRange(
                        new VerseRef($start),
                        $start !== $end ? new VerseRef($end) : null
                    );
                    $chapterRef->addVerseRange($verseRange);
                }
                $chapterRange = new ChapterRange($chapterRef);
                $bookRef->addChapterRange($chapterRange);
            }
            $bookRefs[] = $bookRef;
        }

        return new CanonicalReference($bookRefs, $translationId);
    }

    /**
     * Merge a sorted list of verse numbers into contiguous ranges.
     *
     * @param int[] $verses
     * @return array[] each element is [start, end]
     */
    private function mergeVersesToRanges(array $verses): array
    {
        if (empty($verses)) {
            return [];
        }
        $ranges = [];
        $start = $verses[0];
        $prev = $start;
        for ($i = 1; $i < count($verses); $i++) {
            if ($verses[$i] === $prev + 1) {
                $prev = $verses[$i];
            } else {
                $ranges[] = [$start, $prev];
                $start = $verses[$i];
                $prev = $start;
            }
        }
        $ranges[] = [$start, $prev];
        return $ranges;
    }

    /**
     * Load the verse mapping from config.
     *
     * @return array
     */
    private function getMapping(): array
    {
        if (self::$mapping === null) {
            self::$mapping = config('numbering-scheme.verses', []);
        }
        return self::$mapping;
    }

    /**
     * Load the chapter mapping from config.
     *
     * @return array
     */
    private function getChapterMapping(): array
    {
        if (self::$chapterMapping === null) {
            self::$chapterMapping = config('numbering-scheme.chapters', []);
        }
        return self::$chapterMapping;
    }
}