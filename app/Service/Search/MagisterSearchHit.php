<?php

namespace SzentirasHu\Service\Search;

use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Magister\MagisterPassage;

/**
 * One passage of the meaning search, rendered in every translation that has it.
 */
class MagisterSearchHit
{
    /**
     * @param  array<int, array{translation: Translation, reference: string, verseContainers: array<int, mixed>, highlightedGepis: array<int, string>}>  $translations
     * @param  array<string, ?string>  $citedBy  unit ID => where the document can be read (katolikus.hu), null if unknown
     */
    public function __construct(
        public MagisterPassage $passage,
        public array $translations,
        public array $citedBy,
    ) {}
}
