<?php

namespace Database\Seeders;

use SzentirasHu\Data\Entity\Verse;

class VersesTableSeeder extends \Illuminate\Database\Seeder
{

    public function run()
    {
        $this->addVerse(99101, 1001, 'GEN', 2, 3);
        $this->addVerse(99101, 1001, 'GEN', 2, 4);
        $this->addVerse(99101, 1001, 'GEN', 50, 9);
        $this->addVerse(99102, 1002, 'EXO', 1, 1);
        $this->addVerse(99102, 1002, 'EXO', 2, 3);
        $this->addVerse(99102, 1002, 'EXO', 3, 3);
        $this->addVerse(99201, 1001, 'GEN', 2, 3);
    }

    private function addVerse($bookId, $bookNumber, $usxCode, $chapter, $numv, $opts = [])
    {
        $v = new Verse();
        $v->trans = 1001;
        $v->gepi = sprintf("%d%03d%03d00", $bookNumber, $chapter, $numv);
        $v->usx_code = $usxCode;
        $v->book_id = $bookId;
        $v->chapter = $chapter;
        $v->numv = $numv;
        $v->tip = 6;
        $v->verse = "verse " . $v->hiv;
        $v->verseroot = "verseroot" . $v->hiv;
        $v->save();
    }
}