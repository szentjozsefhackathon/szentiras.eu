<?php

namespace SzentirasHu\Service\Search;

/**
 * The ways the Greek New Testament can be searched. The first two match the two panels of
 * the Greek search form; the third exists for callers that already hold a Strong number,
 * typically from a word by word analysis.
 */
enum GreekSearchMode: string
{
    /**
     * Search among the Strong words (dictionary forms) of the verses, given as a latin
     * transliteration, so every inflected form of the word is found.
     */
    case Lemma = 'lemma';

    /**
     * Search in the Greek text of the verses itself, for the words as they are printed.
     */
    case Verse = 'verse';

    /**
     * Search for the Strong numbers themselves, which identify a dictionary word exactly.
     */
    case Strong = 'strong';
}
