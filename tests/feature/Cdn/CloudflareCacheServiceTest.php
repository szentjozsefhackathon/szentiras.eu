<?php

namespace SzentirasHu\Test\Cdn;

use Illuminate\Support\Facades\Http;
use SzentirasHu\Service\Cdn\CloudflareCacheService;
use SzentirasHu\Test\Common\TestCase;

class CloudflareCacheServiceTest extends TestCase
{
    private function service(?string $zone = 'zone123', ?string $token = 'token456'): CloudflareCacheService
    {
        return new CloudflareCacheService($zone, $token, 'https://api.cloudflare.com/client/v4');
    }

    public function test_purge_everything_calls_cloudflare_api(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'result' => ['id' => 'x']], 200),
        ]);

        $this->assertTrue($this->service()->purgeEverything());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone123/purge_cache'
                && $request['purge_everything'] === true
                && $request->hasHeader('Authorization', 'Bearer token456');
        });
    }

    public function test_purge_tags_sends_only_the_given_tags(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->assertTrue($this->service()->purgeTags(['text-ruf-jhn', 'text-ruf', 'text-ruf']));

        Http::assertSent(function ($request) {
            return $request['tags'] === ['text-ruf-jhn', 'text-ruf']
                && ! isset($request['purge_everything']);
        });
    }

    public function test_purge_tags_splits_large_tag_lists(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $tags = array_map(fn (int $number): string => "tag-{$number}", range(1, 31));
        $this->assertTrue($this->service()->purgeTags($tags));

        Http::assertSentCount(2);
    }

    public function test_purge_is_skipped_when_not_configured(): void
    {
        Http::fake();

        $service = $this->service(null, null);
        $this->assertFalse($service->isConfigured());
        $this->assertFalse($service->purgeEverything());

        Http::assertNothingSent();
    }

    public function test_purge_returns_false_on_api_error(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'bad']]], 403),
        ]);

        $this->assertFalse($this->service()->purgeEverything());
    }
}
