<?php

namespace SzentirasHu\Service\Magister;

/**
 * One Bible passage Magister found for a question. References are in the numbering and book
 * abbreviations of the reference translation (services.magister.reference_translation).
 */
class MagisterPassage
{
    /**
     * @param  array<int, string>  $citedBy  Church document units citing the passage, e.g. "KEK 388"
     * @param  ?string  $matchReference  the verse or range within the passage that matched best
     */
    public function __construct(
        public string $reference,
        public ?string $heading,
        public float $score,
        public array $citedBy,
        public ?string $matchReference,
    ) {}

    /**
     * @param  array{ref: string, heading?: ?string, score?: float, cited_by?: array<int, string>, match?: ?array{ref?: string}}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['ref'],
            $data['heading'] ?? null,
            (float) ($data['score'] ?? 0),
            $data['cited_by'] ?? [],
            $data['match']['ref'] ?? null,
        );
    }
}
