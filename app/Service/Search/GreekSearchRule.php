<?php

namespace SzentirasHu\Service\Search;

/**
 * How the words of a multi word lemma search relate to each other.
 */
enum GreekSearchRule: string
{
    /**
     * Every word must occur in the verse.
     */
    case All = 'all';

    /**
     * Any one of the words is enough.
     */
    case Any = 'any';
}
