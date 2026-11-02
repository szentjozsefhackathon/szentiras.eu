<?php

namespace SzentirasHu\Service\Reference;

use Illuminate\Support\Str;
use SzentirasHu\Data\BookNames;
use SzentirasHu\Data\UsxCodes;

/**
 * Recognises a Bible reference typed the way people type it ("Efezus 2,8", "103. zsoltár",
 * "I. Timóteushoz", "Júdás levele"). The whole input must be a reference, so a quotation or a
 * question never matches. A book without chapter only counts with a book word ("könyve",
 * "levele", "-hoz"), so a bare name like "Sámuel" stays a search.
 */
class ReferenceDetector
{
    private const NUMBER_WORDS = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5,
        'elso' => 1, 'masodik' => 2, 'harmadik' => 3, 'negyedik' => 4, 'otodik' => 5];

    private const FUZZY_MIN_LENGTH = 5;

    /** @var array<string, string>|null */
    private static ?array $abbreviationIndex = null;

    public function detect(string $text): ?DetectedReference
    {
        $normalized = trim(preg_replace('/\s+/', ' ', Str::ascii(mb_strtolower($text, 'UTF-8'))), " .;");
        if ($normalized === '') {
            return null;
        }
        if (preg_match('/^(\d{1,3})\s*\.?\s*zsolt(ar|arok)?$/', $normalized, $m)) {
            return new DetectedReference('PSA', $m[1]);
        }
        $numberWords = implode('|', array_keys(self::NUMBER_WORDS));
        $pattern = "/^(?:(?<num>[1-5])\.?\s*|(?<numword>{$numberWords})(?:\.\s*|\s+))?(?<name>[a-z][a-z .]*?)\s*(?<rest>\d[\d,:.\-a-f]*)?$/";
        if (! preg_match($pattern, $normalized, $m)) {
            return null;
        }
        $number = $m['num'] !== '' ? (int) $m['num'] : ($m['numword'] !== '' ? self::NUMBER_WORDS[$m['numword']] : null);
        $rest = $this->chapterVerses($m['rest'] ?? '');
        if ($rest === null) {
            return null;
        }

        $words = array_values(array_filter(explode(' ', str_replace('.', ' ', $m['name']))));
        $hasBookWord = false;
        $nameWords = [];
        foreach ($words as $word) {
            if (in_array($word, BookNames::BOOK_WORDS, true)) {
                $hasBookWord = true;
            } else {
                $nameWords[] = $word;
            }
        }
        if (empty($nameWords)) {
            return null;
        }
        $last = array_pop($nameWords);
        if (preg_match('/^(.{3,})h[oe]z$/', $last, $suffix)) {
            $last = $suffix[1];
            $hasBookWord = true;
        }
        $nameWords[] = $last;
        if ($rest === '' && ! $hasBookWord) {
            return null;
        }

        $usxCode = $this->resolveBook(implode(' ', $nameWords), $number);

        return $usxCode === null ? null : new DetectedReference($usxCode, $rest);
    }

    /**
     * "6." → "6", "6.1" → "6,1", "12:9" → "12,9"; null when it is not a chapter and verse part.
     */
    private function chapterVerses(string $rest): ?string
    {
        $rest = rtrim(str_replace(':', ',', $rest), '.');
        if (! str_contains($rest, ',') && preg_match('/^(\d+)\.(\d.*)$/', $rest, $m)) {
            $rest = "{$m[1]},{$m[2]}";
        }
        if ($rest !== '' && ! CanonicalReference::isValid("Ter {$rest}")) {
            return null;
        }

        return $rest;
    }

    private function resolveBook(string $name, ?int $number): ?string
    {
        $abbreviation = ($number ?? '') . str_replace(' ', '', $name);
        if (isset($this->abbreviationIndex()[$abbreviation])) {
            return $this->abbreviationIndex()[$abbreviation];
        }
        $key = $number ?? '';
        if (isset(BookNames::NAMES[$name])) {
            return BookNames::NAMES[$name][$key] ?? null;
        }
        if (strlen($name) < self::FUZZY_MIN_LENGTH) {
            return null;
        }
        $candidates = array_filter(array_keys(BookNames::NAMES), fn (string $known): bool => levenshtein($name, $known) === 1);
        if (count($candidates) !== 1) {
            return null;
        }

        return BookNames::NAMES[reset($candidates)][$key] ?? null;
    }

    /**
     * Every abbreviation the parser knows, lowercase and without accents.
     *
     * @return array<string, string>
     */
    private function abbreviationIndex(): array
    {
        if (self::$abbreviationIndex === null) {
            self::$abbreviationIndex = [];
            foreach (array_merge(UsxCodes::OLD_TESTAMENT, UsxCodes::NEW_TESTAMENT) as $mapping) {
                foreach ($mapping as $abbreviations) {
                    foreach ($abbreviations as $abbreviation) {
                        $usxCode = UsxCodes::getUsxFromBookAbbrevAndTranslation($abbreviation);
                        $normalized = str_replace(' ', '', Str::ascii(mb_strtolower($abbreviation, 'UTF-8')));
                        if ($usxCode !== null && ! isset(self::$abbreviationIndex[$normalized])) {
                            self::$abbreviationIndex[$normalized] = $usxCode;
                        }
                    }
                }
            }
        }

        return self::$abbreviationIndex;
    }
}
