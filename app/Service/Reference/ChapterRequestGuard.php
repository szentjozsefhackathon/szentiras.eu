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
        /** @var array<string, array<int, true>> $chaptersByBook */
        $chaptersByBook = [];

        /** @var array<string, true> $wholeBooks */
        $wholeBooks = [];

        foreach ($reference->bookRefs as $bookRef) {
            $bookKey = mb_strtolower($bookRef->bookId);

            if ($bookRef->chapterRanges === []) {
                $wholeBooks[$bookKey] = true;
                $chaptersByBook[$bookKey] = [];
                $chapterCount = $this->wholeBookChapterCount($bookRef->bookId, $translation);

                for ($chapter = 1; $chapter <= $chapterCount; $chapter++) {
                    $chaptersByBook[$bookKey][$chapter] = true;

                    if ($this->chapterCount($chaptersByBook) > self::MAX_CHAPTERS) {
                        return true;
                    }
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
                    $chaptersByBook[$bookKey][$chapter] = true;

                    if ($this->chapterCount($chaptersByBook) > self::MAX_CHAPTERS) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<int, true>>  $chaptersByBook
     */
    private function chapterCount(array $chaptersByBook): int
    {
        return array_sum(array_map('count', $chaptersByBook));
    }

    private function wholeBookChapterCount(string $bookAbbrev, Translation $translation): int
    {
        $book = $this->bookRepository->getByAbbrevForTranslation($bookAbbrev, $translation);

        return $book === null ? 0 : (int) $this->bookService->getChapterCount($book, $translation);
    }
}
