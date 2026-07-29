<?php

namespace SzentirasHu\Service\Ai;

use RuntimeException;
use SzentirasHu\Models\DictionaryEntry;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\StrongWord;

class StrongWordTranslationService
{
    public function __construct(
        protected AiPromptService $aiPromptService,
    ) {}

    /**
     * Generate a dictionary translation for a Strong word using the same OpenAI flow
     * as the szentiras:generate-strong-word-translations command.
     *
     * @param  array<string, mixed>  $configOverrides  Optional overrides (e.g. ['model' => '...'])
     * @return array{translation: array<string, mixed>, tokenUsage: int}
     */
    public function generate(StrongWord $strongWord, array $configOverrides = []): array
    {
        $response = $this->aiPromptService->generate(
            'strong_word_translation',
            false,
            [
                'strong_number' => (string) $strongWord->number,
                'greek_word' => $strongWord->lemma,
                'transliteration' => $strongWord->transliteration,
            ],
            null,
            $configOverrides
        );

        [$responseString, $tokenUsage] = $this->aiPromptService->extractTextAndTokens($response);

        $translation = $this->decodeResponse($responseString);

        return ['translation' => $translation, 'tokenUsage' => $tokenUsage];
    }

    /**
     * Persist a translation into the dictionary tables, replacing any existing entry and meanings.
     *
     * @param  array{word?: string, etymology?: string, notes?: string, meanings?: array<int, array{meaning?: string, explanation?: string}>}  $translation
     */
    public function persist(int $strongWordNumber, array $translation, string $source): DictionaryEntry
    {
        DictionaryEntry::where('strong_word_number', $strongWordNumber)->delete();
        DictionaryMeaning::where('strong_word_number', $strongWordNumber)->delete();

        $dictionaryEntry = new DictionaryEntry;
        $dictionaryEntry->strong_word_number = $strongWordNumber;
        $dictionaryEntry->source = $source;
        $dictionaryEntry->paradigm = $translation['word'] ?? '';
        $dictionaryEntry->etymology = $translation['etymology'] ?? '';
        $dictionaryEntry->notes = $translation['notes'] ?? null;
        $dictionaryEntry->save();

        foreach (array_values($translation['meanings'] ?? []) as $i => $meaning) {
            $dictionaryMeaning = new DictionaryMeaning;
            $dictionaryMeaning->strong_word_number = $strongWordNumber;
            $dictionaryMeaning->source = $source;
            $dictionaryMeaning->meaning = $meaning['meaning'] ?? '';
            $dictionaryMeaning->explanation = $meaning['explanation'] ?? '';
            $dictionaryMeaning->order = $i;
            $dictionaryMeaning->save();
        }

        return $dictionaryEntry;
    }

    /**
     * Decode the JSON response returned by the model, tolerating markdown code fences.
     *
     * @return array<string, mixed>
     */
    private function decodeResponse(string $responseString): array
    {
        $responseString = str_replace(['```json', '```'], '', $responseString);
        $translation = json_decode(trim($responseString), true);

        if (! is_array($translation)) {
            throw new RuntimeException("Bad response from AI: {$responseString}");
        }

        return $translation;
    }
}
