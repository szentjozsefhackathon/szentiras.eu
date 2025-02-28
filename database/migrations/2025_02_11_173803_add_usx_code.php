<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\UsxCodes;

return new class extends Migration {
    public function up(): void
    {
        $prefix = Config::get('database.connections.bible.prefix');

        $bookNumberAndTranslationToUsxMapping =
            $this->bookNumberAndTranslationToUsxMapping();
        $translationIdToTranslationAbbreviationMapping =
            $this->translationIdToTranslationAbbreviationMapping();

        $this->addUsxCodeRelatedColumns(
            $prefix,
            $bookNumberAndTranslationToUsxMapping,
            $translationIdToTranslationAbbreviationMapping
        );

        Schema::table('translations', function (Blueprint $table): void {
            $table->unique('abbrev');
        });

        DB::statement(
            "UPDATE {$prefix}tdverse SET gepi = CONCAT(usx_code, '_', chapter, '_', numv);"
        );

        $this->dropUnnecessaryBookRelatedColumns();
    }

    public function down(): void
    {
        Schema::table('tdverse', function (Blueprint $table): void {
            $table->dropColumn('usx_code');
        });

        Schema::table('translations', function (Blueprint $table): void {
            $table->dropUnique('abbrev');
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn('usx_code');
            $table->renameColumn('order', 'number');
        });

        Schema::table('tdverse', function (Blueprint $table): void {
            $table->integer('book_number');
        });
    }


    private function dropUnnecessaryBookRelatedColumns(): void
    {
        Schema::table('tdverse', function (Blueprint $table): void {
            $table->dropColumn('book_number');
        });
    }

    private function addUsxCodeRelatedColumns(
        $prefix,
        $bookNumberAndTranslationToUsxMapping,
        $translationIdToTranslationAbbreviationMapping
    ): void {
        Schema::table('books', function (Blueprint $table): void {
            $table->renameColumn('number', 'order');
            $table->string('usx_code', 3);
        });

        Schema::table('tdverse', function (Blueprint $table): void {
            $table->string('usx_code', 3);
        });

        $this->updateUsxCodeForBookNumberAndTranslation(
            $prefix,
            $bookNumberAndTranslationToUsxMapping,
            ['tdverse', 'books']
        );
    }

    private function bookNumberAndTranslationToUsxMapping(): array
    {
        $result = [];
        $books = Book::all();
        foreach ($books as $book) {
            $translationAbbrev = $book->translation()->abbrev;
            $bookAbbrev = $book->abbrev;
            $usx = UsxCodes::getUsxFromBookAbbrevAndTranslation($bookAbbrev, $translationAbbrev);
            $key = $this->encodeBookAndTranslation($book->number, $translationAbbrev);
            $result[$key] = $usx;
        }
        return $result;
    }

    private function translationIdToTranslationAbbreviationMapping(): array
    {
        $result = [];
        $translations = Translation::all();
        foreach ($translations as $translation) {
            $result[$translation->id] = $translation->abbrev;
        }
        return $result;
    }

    private function updateUsxCodeForBookNumberAndTranslation(string $prefix, array $mapping, array $tables): void
    {
        $ids = [];
        $caseStatement = "CASE CONCAT(book_number, '|', translation) ";

        foreach ($mapping as $encodedBookAndTranslation => $usxCode) {
            $ids[] = "'{$encodedBookAndTranslation}'";
            $caseStatement .= "WHEN '{$encodedBookAndTranslation}' THEN '{$usxCode}' ";
        }

        $caseStatement .= "END";

        $idsList = implode(',', $ids);

        foreach ($tables as $tableName) {
            DB::statement("UPDATE {$prefix}{$tableName} SET usx_code = {$caseStatement} WHERE CONCAT(book_number, '|', translation) IN ({$idsList})");
        }
    }

    private function isInvalidRow(array $row): bool
    {
        return count($row) !== 2;
    }

    private function encodeBookAndTranslation(int $bookNumber, string $translation): string
    {
        return "{$bookNumber}|{$translation}";
    }
};

