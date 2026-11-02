<?php

namespace SzentirasHu\Service\Reference;

/**
 * A Bible reference recognised in free text: the book and what follows it ("2,8", or '' for the whole book).
 */
class DetectedReference
{
    public function __construct(
        public string $usxCode,
        public string $chapterVerses,
    ) {}
}
