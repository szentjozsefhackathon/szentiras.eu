<?php

namespace SzentirasHu\Mcp;

use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Reference\BookRef;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;

/**
 * Resolves a Hungarian Bible reference to the Greek New Testament verses it covers.
 *
 * The Greek text is stored per USX book code, so the Hungarian book abbreviation of a
 * reference (`Jn`) has to be mapped to its USX code (`JHN`). That mapping comes from the
 * same template translation the site's Greek reader uses
 * ({@see \SzentirasHu\Http\Controllers\Display\GreekTextController}), which holds exactly
 * the 27 New Testament books.
 */
class GreekReferenceResolver
{
    /**
     * The translation whose book list maps Hungarian abbreviations to USX codes.
     */
    private const TEMPLATE_TRANSLATION_ID = 7;

    public function __construct(
        private BookService $bookService,
        private TranslationService $translationService
    ) {
    }

    /**
     * The Greek verses covered by the reference, in canonical order.
     *
     * Books outside the Greek New Testament simply contribute no verses, so an Old
     * Testament reference resolves to an empty collection rather than an error.
     *
     * @return Collection<int, GreekVerse>
     */
    public function versesFor(CanonicalReference $reference): Collection
    {
        $verses = collect();

        foreach ($reference->bookRefs as $bookRef) {
            $usxCode = $this->usxCodeFor($bookRef->bookId);

            if ($usxCode === null) {
                continue;
            }

            $verses = $verses->concat($this->versesForBookRef($bookRef, $usxCode));
        }

        return $verses;
    }

    /**
     * The Hungarian abbreviation belonging to a USX code, used to label verses.
     */
    public function bookAbbrevFor(string $usxCode): ?string
    {
        return $this->templateBooks()->firstWhere('usx_code', $usxCode)?->abbrev;
    }

    /**
     * @return Collection<int, GreekVerse>
     */
    private function versesForBookRef(BookRef $bookRef, string $usxCode): Collection
    {
        $query = GreekVerse::query()
            ->where('usx_code', $usxCode)
            ->orderBy('chapter')
            ->orderBy('verse');

        $chapters = $bookRef->getIncludedChapters();

        // A book-level reference (`Jn`) names no chapters and means the whole book.
        if ($chapters !== []) {
            $query->whereIn('chapter', $chapters);
        }

        $verses = $query->get();

        if ($bookRef->chapterRanges === []) {
            return $verses;
        }

        return $verses
            ->filter(fn (GreekVerse $verse): bool => $this->isCovered($bookRef, $verse))
            ->values();
    }

    /**
     * Whether any of the reference's chapter ranges actually contains the verse, which is
     * what narrows a chapter down to the requested verses of `Jn 3,16-18`.
     */
    private function isCovered(BookRef $bookRef, GreekVerse $verse): bool
    {
        foreach ($bookRef->chapterRanges as $chapterRange) {
            if ($chapterRange->hasVerse($verse->chapter, $verse->verse)) {
                return true;
            }
        }

        return false;
    }

    private function usxCodeFor(string $bookAbbrev): ?string
    {
        return $this->templateBooks()
            ->first(fn (Book $book): bool => mb_strtolower($book->abbrev) === mb_strtolower($bookAbbrev))
            ?->usx_code;
    }

    /**
     * @return Collection<int, Book>
     */
    private function templateBooks(): Collection
    {
        return $this->bookService->getBooksForTranslation(
            $this->translationService->getById(self::TEMPLATE_TRANSLATION_ID)
        );
    }
}
