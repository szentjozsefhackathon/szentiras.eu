<?php

namespace SzentirasHu\Service\Search;

use RuntimeException;

class UnknownBookException extends RuntimeException
{
    public function __construct(public readonly string $book)
    {
        parent::__construct(
            "Unknown book '{$book}'. Use a USX code (e.g. JHN), a Hungarian book abbreviation (e.g. Jn), ".
            'or one of: all, old_testament, new_testament.'
        );
    }
}
