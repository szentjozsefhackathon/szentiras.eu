<?php
/**

 */

namespace SzentirasHu\Data\Repository;


use Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\BookAbbrev;
use SzentirasHu\Data\UsxCodes;

class BookRepositoryEloquent implements BookRepository
{


    public function getBooksByTranslation($translationId)
    {
        return Cache::remember("getBooksByTranslation_{$translationId}", 120, function () use ($translationId) {
            return Book::where('translation_id', $translationId)->orderBy('id')->get();
        });
    }

    /**
     * If translationId is not null, abbrevs associated with the given translation are preferred.
     * If translationId is null, abbrevs not associated with anything are preferred..
     *
     */
    public function getByAbbrev($bookAbbrev, $translationId = null)
    {
        return Cache::remember("book_getByAbbrev_{$bookAbbrev}_{$translationId}", 120, function () use ($bookAbbrev, $translationId) {
            $usxCode = UsxCodes::getUsxFromBookAbbrevAndTranslation($bookAbbrev, $translationId ?? "default");
            if (!$usxCode) {
                return null;
            }

            if ($translationId) {
                $books = Book::where('usx_code', $usxCode)
                    ->where(function ($query) use ($translationId) {
                        $query->where('translation_id', $translationId)
                            ->orWhereNull('translation_id');
                    })
                    ->orderBy('translation_id', 'desc');
            } else {
                $books = Book::where('usx_code', $usxCode)
                    ->orderBy('translation_id', 'asc');
            }
            return $books->first();
        });
    }


    /**
     * @param string $abbrev
     * @param int $translationId
     * @return Book
     */
    public function getByAbbrevForTranslation($abbrev, $translationId)
    {
        return Cache::remember("getBookByUsxCodeForTranslation_{$abbrev}_{$translationId}", 120, function () use ($abbrev, $translationId) {
            $book = $this->getByAbbrev($abbrev, $translationId);
            if ($book) {
                return $this->getByUsxCodeForTranslation($book->usx_code, $translationId);
            }
        });
    }

    public function getByUsxCodeForTranslation($usxCode, $translationId)
    {
        return Cache::remember("getBookByUsxCodeForTranslation_{$usxCode}_{$translationId}", 120, function () use ($translationId, $usxCode) {
            $book = Book::where('usx_code', $usxCode)
                ->where('translation_id', $translationId)
                ->first();
            if ($book == null) {
                return false;
            } else {
                return $book;
            }
        });
    }
}