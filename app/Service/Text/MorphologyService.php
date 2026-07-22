<?php

namespace SzentirasHu\Service\Text;

use Illuminate\Support\Facades\Config;

/**
 * Describes Robinson morphological codes (e.g. `V-PAI-1S`, `N-NSF`) in Hungarian.
 *
 * Every code is looked up whole in `config/morphology.php`, which maps it to its
 * grammatical properties. Codes missing from that map yield an empty string so callers
 * can fall back to showing the raw code.
 */
class MorphologyService
{
    /**
     * The grammatical properties of a code, in the order they are read out.
     *
     * @var array<int, string>
     */
    private const PROPERTY_ORDER = [
        'partOfSpeech',
        'tense',
        'voice',
        'mood',
        'number',
        'person',
        'case',
        'gender',
        'degree',
        'form',
    ];

    /**
     * Describe a morphological code in Hungarian, e.g. `N-NSF` becomes
     * "főnév, egyes szám, alanyeset, nőnem".
     *
     * @return string Empty when the code is absent or unknown.
     */
    public function describe(?string $morphCode): string
    {
        if (blank($morphCode)) {
            return '';
        }

        $morphology = Config::get("morphology.{$morphCode}");

        if (! $morphology) {
            return '';
        }

        $properties = array_map(
            fn (string $property): ?string => $morphology[$property] ?? null,
            self::PROPERTY_ORDER
        );

        return implode(', ', array_filter($properties));
    }
}
