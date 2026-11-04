<?php

namespace SzentirasHu\Service\Search;

/**
 * A heading found by the extended search, in one translation: a title the translators gave a
 * book, section or pericope, not the text of Scripture. Its place is that of the first verse
 * under it, and its word factors mean what they mean for a verse.
 */
class ExtendedSearchHeadingHit
{
    /**
     * @param  string  $text  the heading as the reader sees it, as plain text
     */
    public function __construct(
        public ExtendedSearchHit $hit,
        public string $text,
    ) {}
}
