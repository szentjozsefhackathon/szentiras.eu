<?php
/**

 */

namespace SzentirasHu\Data\Repository;

use Illuminate\Database\Eloquent\Collection;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;

class VerseRepositoryEloquent implements VerseRepository {

    public function getVerses($bookId)
    {
        $verses = Book::find($bookId)->verses()->orderBy('gepi')->orderBy('tip')->orderBy('id')->get();
        return $verses;
    }

    public function getTranslatedChapterVerses($bookId, $chapters, $types = []) : Collection
    {
        $verses = Verse::where('book_id', $bookId);
        if (!empty($chapters)) {
            $verses->whereIn('chapter', $chapters);
        }
        if (!empty($types)) {
            $verses->whereIn('tip', $types);
        }
        return $verses
        ->orderBy('chapter')
        ->orderBy('numv')
        ->orderBy('gepi')
        ->orderBy('tip')
        ->orderBy('id')
        ->get();
        

    }

    public function getLeadVerses($bookId) : Collection
    {
        return Verse::where('book_id', $bookId)
            ->whereIn('numv', ['1', '2'])
            ->orderBy('chapter')
            ->orderBy('numv')
            ->orderBy('gepi')
            ->orderBy('id')
            ->get();
    }

    public function getVersesInOrder($verseIds)
    {
        $verses = Verse::whereIn('id', $verseIds)->with([
            'translation',
            'book'])->get();
        $idVerseMap = [];
        foreach ($verses as $verse) {
            $idVerseMap[$verse->id] = $verse;
        }
        return array_replace(array_flip($verseIds), $idVerseMap);
    }

    public function getMaxChapterByBookUsxCode($usxCode, $translationId)
    {
        return Verse::where('usx_code', $usxCode)->where('trans', $translationId)->max('chapter');
    }

    public function getMaxNumv(Book $book, int $chapter, Translation $translation)
    {
        return Verse::whereBelongsTo($translation)->where("usx_code", $book->usx_code)->where('chapter', $chapter)->max('numv');
    }


}