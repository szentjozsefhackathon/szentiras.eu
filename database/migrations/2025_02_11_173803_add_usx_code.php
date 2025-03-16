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

        $this->addUsxCodeRelatedColumns(
            $prefix,
            $bookNumberAndTranslationToUsxMapping
        );

        Schema::table('translations', function (Blueprint $table): void {
            $table->unique('abbrev');
        });

        // change gepi column type to string
        Schema::table('tdverse', function (Blueprint $table): void {
            $table->string('gepi', 20)->change();
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
            $table->dropUnique('translations_abbrev_unique');
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn('usx_code');
            $table->renameColumn('order', 'number');
        });

        Schema::table('tdverse', function (Blueprint $table): void {
            $table->integer('book_number')->nullable();
            //$table->bigInteger('gepi')->nullable()->change();
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
        $bookNumberAndTranslationToUsxMapping
    ): void {
        Schema::table('books', function (Blueprint $table): void {
            $table->renameColumn('number', 'order');
            $table->string('usx_code', 3)->nullable();
        });

        Schema::table('tdverse', function (Blueprint $table): void {
            $table->string('usx_code', 3);
        });

        $this->updateUsxCodeForBookNumberAndTranslation(
            $prefix,
            $bookNumberAndTranslationToUsxMapping,
            'books',
            'order',
            'translation_id'
        );
    }

    private function bookNumberAndTranslationToUsxMapping(): array
    {
        $result = [];
        $books = Book::all();
        foreach ($books as $book) {
            $translationId = $book->translation->id;
            $translationAbbrev = $book->translation->abbrev;
            $bookAbbrev = $book->abbrev;
            $usx = UsxCodes::getUsxFromBookAbbrevAndTranslation($bookAbbrev, $translationAbbrev);
            $key = $this->encodeBookAndTranslation($book->number, $translationId);
            $result[$key] = $usx;
        }
        return $result;
    }

    private function translationAbbrevToIdMapping(): array
    {
        $result = [];
        $translations = Translation::all();
        foreach ($translations as $translation) {
            $result[$translation->abbrev] = $translation->id;
        }
        return $result;
    }

    private function updateUsxCodeForBookNumberAndTranslation(
        string $prefix,
        array $mapping,
        string $tableName,
        string $bookColumn,
        string $transColumn
    ): void {
        $ids = [];
        $caseStatement = "CASE CONCAT(\"{$bookColumn}\", '|', \"{$transColumn}\") ";

        foreach ($mapping as $encodedBookAndTranslation => $usxCode) {
            $ids[] = "'{$encodedBookAndTranslation}'";
            $caseStatement .= "WHEN '{$encodedBookAndTranslation}' THEN '{$usxCode}' ";
        }

        $caseStatement .= "END";

        $idsList = implode(',', $ids);

        $statement = "UPDATE {$prefix}{$tableName} SET usx_code = {$caseStatement} 
            WHERE CONCAT(\"{$bookColumn}\", '|', \"{$transColumn}\") IN ({$idsList})";
        DB::statement($statement);
    }

    private function isInvalidRow(array $row): bool
    {
        return count($row) !== 2;
    }

    private function encodeBookAndTranslation(int $bookNumber, int $translation): string
    {
        return "{$bookNumber}|{$translation}";
    }
};

