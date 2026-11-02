<?php

namespace SzentirasHu\Service\Magister;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client of Magister (magister.szentiras.eu), the Church-document source finder of the
 * Szent József Hackathon. Its Bible search ranks passages by meaning and lifts the ones the
 * Catechism and other Church documents cite, so "áteredő bűn" finds Róm 5,12-21.
 */
class MagisterClient
{
    private const SEARCH_CACHE_TTL_HOURS = 24;

    /**
     * Bible passages for a question, the best first.
     *
     * @return array<int, MagisterPassage>
     *
     * @throws MagisterUnavailableException
     */
    public function searchBible(string $question, int $limit = 30): array
    {
        $cacheKey = 'magister_searchBible_' . md5($question) . "_{$limit}";
        $passages = Cache::get($cacheKey);
        if ($passages === null) {
            $passages = $this->fetchPassages($question, $limit);
            Cache::put($cacheKey, $passages, now()->addHours(self::SEARCH_CACHE_TTL_HOURS));
        }

        return array_map(fn (array $passage): MagisterPassage => MagisterPassage::fromArray($passage), $passages);
    }

    /**
     * Magister's units link to its question page, which resolves a unit ID given as the question.
     */
    public function unitUrl(string $unitId): string
    {
        return $this->questionUrl($unitId);
    }

    public function questionUrl(string $question): string
    {
        return rtrim(config('services.magister.url'), '/') . '/?' . http_build_query(['q' => $question], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPassages(string $question, int $limit): array
    {
        try {
            $response = Http::baseUrl(config('services.magister.url'))
                ->timeout((int) config('services.magister.timeout'))
                ->acceptJson()
                ->get('/api/szentiras_kereses', ['q' => $question, 'limit' => $limit])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            Log::warning("Magister Bible search failed: {$e->getMessage()}");
            throw new MagisterUnavailableException('Magister Bible search failed.', 0, $e);
        }

        $passages = $response->json('passages');
        if (! is_array($passages)) {
            Log::warning('Magister Bible search returned no passage list.');
            throw new MagisterUnavailableException('Magister Bible search returned no passage list.');
        }

        return array_values(array_filter($passages, fn ($passage): bool => is_array($passage) && is_string($passage['ref'] ?? null)));
    }
}
