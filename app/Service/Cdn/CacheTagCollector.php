<?php

namespace SzentirasHu\Service\Cdn;

/**
 * Collects the Cloudflare cache tags of the current request, so a CDN-cached page
 * can later be purged by the texts it shows instead of purging the whole zone.
 * Bound as a scoped singleton: one instance per request.
 */
class CacheTagCollector
{
    /**
     * @var array<string, true>
     */
    private array $tags = [];

    public static function textTag(string $translationAbbrev, string $usxCode): string
    {
        return strtolower("text-{$translationAbbrev}-{$usxCode}");
    }

    public static function translationTag(string $translationAbbrev): string
    {
        return strtolower("text-{$translationAbbrev}");
    }

    public function add(string $tag): void
    {
        $this->tags[$tag] = true;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->tags);
    }
}
