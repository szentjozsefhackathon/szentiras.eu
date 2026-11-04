<?php

namespace SzentirasHu\Service\Search;

use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Service\Magister\MagisterClient;
use SzentirasHu\Service\Magister\MagisterPassage;
use SzentirasHu\Service\Magister\MagisterUnavailableException;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

/**
 * The meaning search of /ai-search, answered by Magister rather than our own vectors: Magister
 * ranks pericopes and lifts the ones the Church documents cite (the "bridge"), which finds the
 * passages a question is really about. We only render its references in our translations.
 * Magister is not public yet: nothing on the page names it or links to it, the citing documents
 * link to katolikus.hu (ChurchDocumentLinks).
 */
class MagisterSemanticSearchService
{
    /** Magister has no book filter, so candidates are fetched generously and filtered here. */
    private const CANDIDATES = 30;

    public const MAX_HITS = 10;

    public function __construct(
        protected MagisterClient $magisterClient,
        protected TranslationService $translationService,
        protected TextService $textService,
        protected ReferenceService $referenceService,
        protected BookRepository $bookRepository,
        protected ChurchDocumentLinks $churchDocumentLinks,
    ) {}

    /**
     * @param  array<int, string>  $usxCodes  books to keep; empty keeps all
     * @return array<int, MagisterSearchHit>
     *
     * @throws MagisterUnavailableException
     */
    public function search(string $question, ?string $translationAbbrev = null, array $usxCodes = []): array
    {
        $referenceTranslation = $this->translationService->getByAbbreviation(config('services.magister.reference_translation'));
        $translations = $this->displayedTranslations($translationAbbrev);

        $hits = [];
        foreach ($this->magisterClient->searchBible($question, self::CANDIDATES) as $passage) {
            $hit = $this->toHit($passage, $referenceTranslation, $translations, $usxCodes);
            if ($hit !== null) {
                $hits[] = $hit;
            }
            if (count($hits) >= self::MAX_HITS) {
                break;
            }
        }

        return $hits;
    }

    /**
     * @return Collection<int, Translation>
     */
    private function displayedTranslations(?string $translationAbbrev): Collection
    {
        $translations = $this->translationService->getAllTranslations();
        if (! empty($translationAbbrev)) {
            return $translations->where('abbrev', $translationAbbrev)->values();
        }

        return $translations;
    }

    /**
     * @param  Collection<int, Translation>  $translations
     * @param  array<int, string>  $usxCodes
     */
    private function toHit(MagisterPassage $passage, Translation $referenceTranslation, Collection $translations, array $usxCodes): ?MagisterSearchHit
    {
        try {
            $reference = CanonicalReference::fromString($passage->reference, $referenceTranslation->id);
            $matchReference = $passage->matchReference !== null
                ? CanonicalReference::fromString($passage->matchReference, $referenceTranslation->id)
                : null;
        } catch (ParsingException) {
            return null;
        }

        $book = $this->bookRepository->getByAbbrevForTranslation($reference->bookRefs[0]->bookId, $referenceTranslation);
        if (! $book || (! empty($usxCodes) && ! in_array($book->usx_code, $usxCodes, true))) {
            return null;
        }

        $rendered = [];
        foreach ($translations as $translation) {
            $verseContainers = $this->textService->getTranslatedVerses($reference, $translation);
            if (! $this->hasVerses($verseContainers)) {
                continue;
            }
            $rendered[] = [
                'translation' => $translation,
                'reference' => $this->referenceService->translateReference($reference, $translation->id)->toString(),
                'verseContainers' => $verseContainers,
                'highlightedGepis' => $matchReference ? $this->gepis($matchReference, $translation) : [],
            ];
        }
        if (empty($rendered)) {
            return null;
        }

        $citedBy = [];
        foreach ($passage->citedBy as $unitId) {
            $citedBy[$unitId] = $this->churchDocumentLinks->urlOf($unitId);
        }

        return new MagisterSearchHit($passage, $rendered, $citedBy);
    }

    /**
     * @param  array<int, mixed>  $verseContainers
     */
    private function hasVerses(array $verseContainers): bool
    {
        foreach ($verseContainers as $verseContainer) {
            if (! empty($verseContainer->rawVerses)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function gepis(CanonicalReference $reference, Translation $translation): array
    {
        $gepis = [];
        foreach ($this->textService->getTranslatedVerses($reference, $translation) as $verseContainer) {
            foreach (array_keys($verseContainer->rawVerses) as $gepi) {
                $gepis[] = "{$gepi}";
            }
        }

        return $gepis;
    }
}
