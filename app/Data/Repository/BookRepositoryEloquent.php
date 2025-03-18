<?php
/**

 */

namespace SzentirasHu\Data\Repository;


use Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\BookAbbrev;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\UsxCodes;

class BookRepositoryEloquent implements BookRepository
{


    public function getBooksByTranslation($translationId)
    {
        return Cache::remember("getBooksByTranslation_{$translationId}", 120, function () use ($translationId) {
            return Book::where('translation_id', $translationId)->orderBy('order')->get();
        });
    }

    /**
     * If translationId is not null, abbrevs associated with the given translation are preferred.
     * If translationId is null, abbrevs not associated with anything are preferred..
     *
     */
    public function getByAbbrev(string $bookAbbrev, ?Translation $translation) : ?Book
    {
        $translationAbbrev = $translation->abbrev ?? "default";
        return Cache::remember("book_getByAbbrev_{$bookAbbrev}_{$translationAbbrev}", 120, function () use ($bookAbbrev, $translationAbbrev, $translation) {
            $usxCode = UsxCodes::getUsxFromBookAbbrevAndTranslation($bookAbbrev, $translationAbbrev);
            if (!$usxCode) {
                return null;
            }

            if ($translation) {
                $books = Book::where('usx_code', $usxCode)
                    ->where(function ($query) use ($translation) {
                        $query->whereBelongsTo($translation)
                            ->orWhereNull('translation_id');
                    })
                    ->orderBy('translation_id', 'desc');
            } else {
                $books = Book::where('usx_code', $usxCode)
                    ->orderBy('translation_id', 'asc');
            }
            return $books->with('translation')->first();
        });
    }


    /**
     * @param string $abbrev
     * @param Translation $translation
     * @return Book
     */
    public function getByAbbrevForTranslation($abbrev, Translation $translation) : ?Book
    {
        $usxCode = UsxCodes::getUsxFromBookAbbrevAndTranslation($abbrev, $translation->abbrev);
        return $this->getByUsxCodeForTranslation($usxCode, $translation);
    }

    public function getByUsxCodeForTranslation(string $usxCode, Translation $translation)
    {
        return Cache::remember("getBookByUsxCodeForTranslation_{$usxCode}_{$translation->id}", 120, function () use ($translation, $usxCode) {
            $book = Book::where('usx_code', $usxCode)
                ->whereBelongsTo($translation)
                ->with('translation')
                ->first();
            if ($book == null) {
                return false;
            } else {
                return $book;
            }
        });
    }
}
