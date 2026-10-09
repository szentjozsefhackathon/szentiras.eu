<?php

namespace SzentirasHu\Service\Cdn;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareCacheService
{
    public function __construct(
        private ?string $zoneId,
        private ?string $apiToken,
        private string $apiBaseUrl,
    ) {
    }

    public function isConfigured(): bool
    {
        return ! empty($this->zoneId) && ! empty($this->apiToken);
    }

    /**
     * Cloudflare accepts at most this many tags in one purge request.
     */
    private const MAX_TAGS_PER_REQUEST = 30;

    /**
     * Purge the entire Cloudflare cache for the configured zone. Used after
     * imports and deploys, when every text may have changed.
     */
    public function purgeEverything(): bool
    {
        return $this->purge(['purge_everything' => true]);
    }

    /**
     * Purge only the cached pages carrying any of the given Cache-Tag values.
     *
     * @param  list<string>  $tags
     */
    public function purgeTags(array $tags): bool
    {
        $purged = true;
        foreach (array_chunk(array_values(array_unique($tags)), self::MAX_TAGS_PER_REQUEST) as $tagChunk) {
            $purged = $this->purge(['tags' => $tagChunk]) && $purged;
        }

        return $purged;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function purge(array $payload): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Cloudflare cache purge skipped: zone id or API token not configured.');

            return false;
        }

        $response = Http::withToken($this->apiToken)
            ->acceptJson()
            ->post("{$this->apiBaseUrl}/zones/{$this->zoneId}/purge_cache", $payload);

        if ($response->successful() && $response->json('success') === true) {
            return true;
        }

        Log::error('Cloudflare cache purge failed.', [
            'payload' => $payload,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }
}
