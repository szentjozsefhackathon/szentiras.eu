<?php

namespace SzentirasHu\Http\Controllers\Ai;

use Exception;
use Illuminate\Http\Request;
use League\CommonMark\Reference\Reference;
use Pgvector\Vector;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ReferenceService;
use SzentirasHu\Service\Search\SemanticSearchService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

class AiController extends Controller
{

    public function __construct(
        protected TextService $textService,
        protected SemanticSearchService $semanticSearchService,
        protected TranslationService $translationService,
        protected ReferenceService $referenceService
    ) {}

    public function getAiToolPopover($translationAbbrev, $reference)
    {
        $hash = md5($reference);
        $allTranslations = $this->translationService->getAllTranslations();
        $translation = $this->translationService->getByAbbreviation($translationAbbrev);
        $canonicalReference = CanonicalReference::fromString($reference, $translation->id);
        $pureTexts[] = [
            'translationAbbrev' => $translationAbbrev,
            'reference' => $canonicalReference->toString(),
            'text' => $this->textService->getPureText(CanonicalReference::fromString($reference, $translation->id), $translation, false),
        ];
        $vector1 = $this->semanticSearchService->retrieveVector($canonicalReference->toString(), $translationAbbrev);
        foreach ($allTranslations as $otherTranslation) {
            if ($otherTranslation->abbrev != $translationAbbrev) {
                $translatedReference = $this->referenceService->translateReference($canonicalReference, $otherTranslation->id)->toString();
                $otherText = $this->textService->getPureText(CanonicalReference::fromString($reference, $otherTranslation->id), $otherTranslation, false);
                if (!empty($otherText)) {
                    $vector2 = $this->semanticSearchService->retrieveVector($translatedReference, $otherTranslation->abbrev);
                    if ($vector1 && $vector2) {
                        $similarity = $this->semanticSearchService->calculateSimilarity($vector1, $vector2);
                    } else {
                        $similarity = null;
                    }
                    $pureTexts[] = [
                        'translationAbbrev' => $otherTranslation->abbrev,
                        'reference' => $translatedReference,
                        'text' => $otherText,
                        'similarity' => $similarity
                    ];
                }
            }
        }
        $similarExcerpts = $this->semanticSearchService->findSimilarVersesInTranslation($canonicalReference->toString(), $translationAbbrev);
        if (!empty($similarExcerpts)) {
            foreach ($similarExcerpts as $excerpt) {
                $similars[] = [
                    "reference" => $excerpt->reference,
                    "translationAbbrev" => $excerpt->translation_abbrev,
                    "similarity" => 1 - $excerpt->neighbor_distance,
                    "text" => $this->textService->getPureText(CanonicalReference::fromString($excerpt->reference, $excerpt->translation_id), $this->translationService->getByAbbreviation($excerpt->translation_abbrev), false)
                ];
            }
        }

        $view = view("ai.aiToolPopover", ['pureTexts' => $pureTexts ?? [], 'similars' => $similars ?? [], 'hash' => $hash])->render();
        return response()->json($view);
    }
}
