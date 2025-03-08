<?php

namespace SzentirasHu\Service\Search;

use Config;
use Exception;
use Log;
use OpenAI\Laravel\Facades\OpenAI;
use Pgvector\Laravel\Distance;
use Pgvector\Vector;
use SzentirasHu\Data\Entity\EmbeddedExcerpt;
use SzentirasHu\Data\Entity\EmbeddedExcerptScope;
use SzentirasHu\Http\Controllers\Search\SemanticSearchForm;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

class EmbeddingResult {
    /**
     * @param array<float> $vector
     */
    public function __construct(public array $vector, public int $totalTokens ) {
    }
}

class SemanticSearchParams {

    public $text;
    public $translationAbbrev;
    public $usxCodes;

}

class SemanticSearchService {

    public function __construct(protected TextService $textService, protected TranslationService $translationService, protected BookService $bookService) {
    }

    public function generateVector(string $text, string $model = null, int $dimensions = null) : EmbeddingResult{
        if (is_null($model)) {
            $model = Config::get("settings.ai.embeddingModel");
        }
        if (is_null($dimensions)) {
            $dimensions = Config::get("settings.ai.embeddingDimensions");
        }
        return \Cache::remember("generateVector_{$text}_{$model}_{$dimensions}", 24*60*60, function () use ($text, $model, $dimensions) {
            $response = OpenAI::embeddings()->create([
                'model' => $model,
                'input' => $text,
                'dimensions' => $dimensions,
                'user' => "szentiras.eu"
            ]);
            $vector = $response->embeddings[0]->embedding;
            $totalTokens = $response->usage->totalTokens;
            Log::info("OpenAI request finished, total tokens: {$totalTokens}");
            return new EmbeddingResult($vector, $totalTokens); 
        });

    }

    public function findNeighbors(SemanticSearchParams $params, array $vector, $scope = EmbeddedExcerptScope::Verse, $maxResults = 10, $metric = Distance::Cosine, string $model = null) : SemanticSearchResponse {        
        if (is_null($model)) {
            $model = Config::get("settings.ai.embeddingModel");
        }
        $neighbors = EmbeddedExcerpt::query()
            ->where("scope", $scope)
            ->where("model", $model)
            ->nearestNeighbors("embedding", $vector, $metric);
        if (!empty($params->translationAbbrev)) {
            $neighbors->where("translation_abbrev", $params->translationAbbrev);
        }
        if (!empty($params->bookNumbers)) {
            $neighbors->whereIn("usx_code", $params->usxCodes);
        }
        $neighbors = $neighbors->limit($maxResults)->get();
        // if we are looking for chapters, look for the most relevant verse in the chapter
        $topVerseContainers = [];
        $results = [];
        foreach ($neighbors as $neighbor) {
            if ($scope == EmbeddedExcerptScope::Chapter || $scope == EmbeddedExcerptScope::Range) {
                $topVerseInChapter = EmbeddedExcerpt::query()
                    ->nearestNeighbors("embedding", $vector, $metric)
                    ->where("model", $model)
                    ->where("usx_code", $neighbor->usx_code)
                    ->where("translation_abbrev", $neighbor->translation_abbrev)
                    ->where("scope", EmbeddedExcerptScope::Verse);
                if ($scope == EmbeddedExcerptScope::Chapter) {
                    $topVerseInChapter = $topVerseInChapter
                        ->where("chapter",  $neighbor->chapter);
                } else if ($scope == EmbeddedExcerptScope::Range) {
                    $pointerFrom = $neighbor->chapter * 10000 + $neighbor->verse;
                    $pointerTo = $neighbor->to_chapter * 10000 + $neighbor->to_verse;
                    $topVerseInChapter = $topVerseInChapter->whereRaw(
                        "chapter * 10000 + verse >= ? AND chapter * 10000 + verse <= ?",
                        [$pointerFrom, $pointerTo]);
                }
                $topVerseInChapter = $topVerseInChapter->first();
                if (!empty($topVerseInChapter)) {
                    $topVerseTranslation = $this->translationService->getByAbbreviation($topVerseInChapter->translation_abbrev);                    
                    $topVerseContainers = $this->textService->getTranslatedVerses(CanonicalReference::fromString($topVerseInChapter->reference, $topVerseTranslation->id), $topVerseTranslation);                
                }
            }
            $result = new SemanticSearchResult;
            $result->embeddedExcerpt = $neighbor;
            $result->distance = $neighbor->neighbor_distance;
            $neighborTranslation = $this->translationService->getByAbbreviation($neighbor->translation_abbrev);
            $result->verseContainers = $this->textService->getTranslatedVerses(CanonicalReference::fromString($neighbor->reference, $neighborTranslation->id), $neighborTranslation);
            $result->quality=$this->getQualityScore($neighbor->neighbor_distance, $metric, $scope);
            $highlightedGepis = [];
            foreach ($topVerseContainers as $verseContainer) {
                $highlightedGepis = array_map(fn($k) => "{$k}",array_keys($verseContainer->rawVerses));
            }
            $result->highlightedGepis = $highlightedGepis;
            $results[] = $result;
        }        
        $response = new SemanticSearchResponse($results, $metric);
        return $response;
    }

    private function getQualityScore(float $distance, Distance $metric, EmbeddedExcerptScope $scope) {        
        $value = $distance;
        if ($metric == Distance::Cosine) {
            if ($value < .4) {
                return 5;
            } else if ($value <.5) {
                return 4;
            } else if ($value <.6) {
                return 3;
            } else if ($value <.7) {
                return 2;
            } else {
                return 1;
            }
        } else {
            if ($value < .8) {
                return 5;
            } else if ($value <.9) {
                return 4;
            } else if ($value <1) {
                return 3;
            } else if ($value <1.1) {
                return 2;
            } else {
                return 1;
            }
        }
        
        return $value;
    }

    /**
     * Returns the most similar verses in the same translation.
     */
    public function findSimilarVersesInTranslation($reference, $translationAbbrev, $limit = 10) {
        $model = Config::get("settings.ai.embeddingModel");
        $scope = EmbeddedExcerptScope::Verse;
        $vector = EmbeddedExcerpt::query()
        ->where("reference", $reference)
        ->where("translation_abbrev", $translationAbbrev)            
        ->where("scope", $scope)
        ->where("model", $model)
        ->first();
        if (empty($vector)) {
            return null;
        }
        return EmbeddedExcerpt::query()
        ->where("reference", "!=", $reference)
        ->where("translation_abbrev", $translationAbbrev)            
        ->where("scope", $scope)
        ->where("model", $model)
        ->nearestNeighbors("embedding", $vector->embedding, Distance::Cosine)
        ->limit($limit)
        ->get();

    }

    public function retrieveVector($reference, $translationAbbrev, $scope = EmbeddedExcerptScope::Verse) {
        $model = Config::get("settings.ai.embeddingModel");
        $vector = EmbeddedExcerpt::query()
            ->where("reference", $reference)
            ->where("translation_abbrev", $translationAbbrev)            
            ->where("scope", $scope)
            ->where("model", $model)
            ->first();
        if (empty($vector)) {
            return null;
        }
        return $vector->embedding;
    }

    public function calculateSimilarity(Vector $v1, Vector $v2) {
        return $this->cosineSimilarity($v1, $v2);
    }

    function cosineSimilarity(Vector $v1, Vector $v2)
    {
        $components1 = $v1->toArray();
        $components2 = $v2->toArray();

        // Ensure both vectors have the same dimensionality
        if (count($components1) !== count($components2)) {
            throw new Exception('Vectors must have the same number of dimensions.');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        $length = count($components1);

        for ($i = 0; $i < $length; $i++) {
            $x = $components1[$i];
            $y = $components2[$i];

            $dotProduct += $x * $y;
            $magnitude1 += $x * $x;
            $magnitude2 += $y * $y;
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            // If either vector has zero magnitude, cosine similarity is undefined
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    function normalizeVector(Vector $vector)
    {
        $components = $vector->toArray();
        $magnitude = sqrt(array_reduce($components, function ($carry, $item) {
            return $carry + $item * $item;
        }, 0));

        if ($magnitude == 0) {
            return new Vector($components);
        }

        $normalizedComponents = array_map(function ($item) use ($magnitude) {
            return $item / $magnitude;
        }, $components);

        return new Vector($normalizedComponents);
    }

}