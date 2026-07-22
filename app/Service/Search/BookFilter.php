<?php

namespace SzentirasHu\Service\Search;

use SzentirasHu\Data\UsxCodes;

/**
 * Turns the book selection of a search into the USX codes to search in.
 *
 * The search form offers the whole Bible, either testament, or a single book chosen from a
 * list of USX codes. The API accepts the same values, and additionally a Hungarian book
 * abbreviation, so that a caller who works with references (`Jn`) does not have to know the
 * USX code (`JHN`).
 */
class BookFilter
{
    public const ALL = 'all';

    public const OLD_TESTAMENT = 'old_testament';

    public const NEW_TESTAMENT = 'new_testament';

    /**
     * @return array<string, mixed> The books to search in, keyed by USX code. Empty means
     *                              the whole Bible.
     *
     * @throws UnknownBookException
     */
    public static function usxCodesFor(?string $book): array
    {
        $book = trim((string) $book);

        if ($book === '' || $book === self::ALL) {
            return [];
        }

        if ($book === self::OLD_TESTAMENT) {
            return UsxCodes::OLD_TESTAMENT;
        }

        if ($book === self::NEW_TESTAMENT) {
            return UsxCodes::NEW_TESTAMENT;
        }

        $usxCode = mb_strtoupper($book);

        if (in_array($usxCode, UsxCodes::allUsx(), true)) {
            return [$usxCode => true];
        }

        $usxCode = UsxCodes::getUsxFromBookAbbrevAndTranslation($book);

        if ($usxCode === null) {
            throw new UnknownBookException($book);
        }

        return [$usxCode => true];
    }
}
