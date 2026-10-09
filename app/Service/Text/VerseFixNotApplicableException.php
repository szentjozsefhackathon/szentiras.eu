<?php

namespace SzentirasHu\Service\Text;

use RuntimeException;

/**
 * Thrown when a verse fix cannot be applied because none of the expected texts is stored in the verse.
 */
class VerseFixNotApplicableException extends RuntimeException {}
