<?php

namespace SzentirasHu\Service\Reference;

use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Service\Text\BookService;

final class ChapterRequestGuard
{
    public const MAX_CHAPTERS = 5;

    public function __construct(
        private BookRepository $bookRepository,
        private BookService $bookService
    ) {}

    public function exceedsLimit(CanonicalReference $reference, Translation $translation): bool
    {
        $collected = $this->collectChapters($reference, $translation, self::MAX_CHAPTERS + 1);

        return $this->chapterCount($collected) > self::MAX_CHAPTERS;
    }

    /**
     * Returns the beginning of the requested reference, trimmed to the maximum number of
     * chapters that can be displayed at once, so it can be offered to the user as an
     * alternative. Returns null if nothing could be collected from the reference.
     */
    public function suggestReference(CanonicalReference $reference, Translation $translation): ?CanonicalReference
    {
        $collected = $this->collectChapters($reference, $translation, self::MAX_CHAPTERS);
        $suggestion = new CanonicalReference([], $reference->translationId);

        foreach ($collected as $book) {
            $chapters = array_keys($book['chapters']);
            if ($chapters === []) {
                continue;
            }
            sort($chapters);
            $bookRef = new BookRef($book['bookId']);
            foreach ($this->groupToRanges($chapters) as [$firstChapter, $lastChapter]) {
                $bookRef->addChapterRange(new ChapterRange(
                    new ChapterRef($firstChapter),
                    $firstChapter === $lastChapter ? null : new ChapterRef($lastChapter)
                ));
            }
            $suggestion->addBookRef($bookRef);
        }

        return $suggestion->bookRefs === [] ? null : $suggestion;
    }

    /**
     * Collects the distinct chapters of the reference, book by book, and stops as soon as
     * $limit chapters have been gathered.
     *
     * @return array<string, array{bookId: string, chapters: array<int, true>}>
     */
    private function collectChapters(CanonicalReference $reference, Translation $translation, int $limit): array
    {
        /** @var array<string, array{bookId: string, chapters: array<int, true>}> $collected */
        $collected = [];

        /** @var array<string, true> $wholeBooks */
        $wholeBooks = [];

        foreach ($reference->bookRefs as $bookRef) {
            $bookKey = mb_strtolower($bookRef->bookId);

            if ($bookRef->chapterRanges === []) {
                if (isset($wholeBooks[$bookKey])) {
                    continue;
                }
                $wholeBooks[$bookKey] = true;
                $collected[$bookKey] = ['bookId' => $bookRef->bookId, 'chapters' => []];
                $chapterCount = $this->wholeBookChapterCount($bookRef->bookId, $translation);

                for ($chapter = 1; $chapter <= $chapterCount; $chapter++) {
                    if ($this->chapterCount($collected) >= $limit) {
                        return $collected;
                    }
                    $collected[$bookKey]['chapters'][$chapter] = true;
                }

                continue;
            }

            if (isset($wholeBooks[$bookKey])) {
                continue;
            }

            foreach ($bookRef->chapterRanges as $chapterRange) {
                $firstChapter = $chapterRange->chapterRef->chapterId;
                $lastChapter = max(
                    $firstChapter,
                    $chapterRange->untilChapterRef
                        ? $chapterRange->untilChapterRef->chapterId
                        : $firstChapter
                );

                for ($chapter = $firstChapter; $chapter <= $lastChapter; $chapter++) {
                    if (isset($collected[$bookKey]['chapters'][$chapter])) {
                        continue;
                    }
                    if ($this->chapterCount($collected) >= $limit) {
                        return $collected;
                    }
                    $collected[$bookKey] ??= ['bookId' => $bookRef->bookId, 'chapters' => []];
                    $collected[$bookKey]['chapters'][$chapter] = true;
                }
            }
        }

        return $collected;
    }

    /**
     * @param  int[]  $chapters  sorted, distinct chapter numbers
     * @return array<int, array{0: int, 1: int}>
     */
    private function groupToRanges(array $chapters): array
    {
        $ranges = [];
        $firstChapter = $lastChapter = array_shift($chapters);
        foreach ($chapters as $chapter) {
            if ($chapter === $lastChapter + 1) {
                $lastChapter = $chapter;
                continue;
            }
            $ranges[] = [$firstChapter, $lastChapter];
            $firstChapter = $lastChapter = $chapter;
        }
        $ranges[] = [$firstChapter, $lastChapter];

        return $ranges;
    }

    /**
     * @param  array<string, array{bookId: string, chapters: array<int, true>}>  $collected
     */
    private function chapterCount(array $collected): int
    {
        return array_sum(array_map(fn (array $book): int => count($book['chapters']), $collected));
    }

    private function wholeBookChapterCount(string $bookAbbrev, Translation $translation): int
    {
        $book = $this->bookRepository->getByAbbrevForTranslation($bookAbbrev, $translation);

        return $book === null ? 0 : (int) $this->bookService->getChapterCount($book, $translation);
    }
}
