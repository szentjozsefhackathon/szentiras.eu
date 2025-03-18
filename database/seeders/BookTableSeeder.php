<?php

namespace Database\Seeders;

use SzentirasHu\Data\UsxCodes;


/**

 */
class BookTableSeeder extends \Illuminate\Database\Seeder
{
    public function run()
    {
        \Log::info('Running book table seeder');
        $translation = $this->addTranslation(1001, 'Translation Name 1', 'TESTTRANS');
        $this->addBook(99101, 101, "Ter", $translation);
        $this->addBook(99102, 102, "Kiv", $translation);
        $this->addBook(99103, 103, "Lev", $translation);
        $this->addBook(99104, 104, "Szám", $translation);

        $translation = $this->addTranslation(1002, 'Translation Name 2', 'TESTTRANS2');
        $this->addBook(99201, 101, "1Móz", $translation);
        $this->addBook(99202, 102, "2Móz", $translation);
    }

    private function addBook($id, $order, $abbrev, $translation)
    {
        $book = new \SzentirasHu\Data\Entity\Book();
        $book->id = $id;
        $book->order = $order;
        $book->abbrev = $abbrev;
        $book->name = "$abbrev.";
        $book->link = "link";
        $book->old_testament = 1;
        $book->usx_code = UsxCodes::getUsxFromBookAbbrevAndTranslation($abbrev);
        $book->translation()->associate($translation);
        $book->save();
        return $book;
    }

    private function addTranslation($id, $name, $abbrev)
    {
        $translation = new \SzentirasHu\Data\Entity\Translation();
        $translation->id = $id;
        $translation->name = $name;
        $translation->abbrev = $abbrev;
        $translation->denom = "denom";
        $translation->lang = "hu";
        $translation->save();
        return $translation;
    }

} 
