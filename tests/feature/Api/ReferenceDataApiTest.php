<?php

namespace SzentirasHu\Test\Api;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\UsxCodes;
use SzentirasHu\Service\Reference\ReferenceDataService;
use SzentirasHu\Test\Common\TestCase;

/**
 * The reference data is public and key-less. The index is cached briefly and names the current
 * version; the versioned document is cached forever, which is only safe because its URL changes
 * whenever its content does.
 */
class ReferenceDataApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['api.key_required' => true]);

        Cache::flush();
    }

    public function test_index_names_current_version_without_api_key(): void
    {
        $version = app(ReferenceDataService::class)->version();

        $response = $this->get('/api/reference-data');

        $response->assertOk();
        $response->assertExactJson([
            'version' => $version,
            'url' => route('api.reference-data.show', ['version' => $version]),
        ]);
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_versioned_document_is_immutable_and_hashes_to_its_version(): void
    {
        $version = $this->get('/api/reference-data')->json('version');

        $response = $this->get("/api/reference-data/{$version}");

        $response->assertOk();
        $this->assertSame($version, hash('sha256', $response->getContent()));
        $this->assertSame(UsxCodes::OLD_TESTAMENT['GEN'], $response->json('books.GEN.abbrevs'));
        $this->assertSame('old', $response->json('books.GEN.testament'));
        $this->assertSame('new', $response->json('books.MAT.testament'));
        $this->assertSame(config('numbering-scheme'), $response->json('numbering.vulgata'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    public function test_unknown_version_is_not_found_and_not_cached(): void
    {
        $response = $this->get('/api/reference-data/' . str_repeat('0', 64));

        $response->assertNotFound();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_version_changes_with_the_data(): void
    {
        $before = app(ReferenceDataService::class)->version();

        config(['numbering-scheme.verses' => ['changed' => true]]);

        $this->assertNotSame($before, app(ReferenceDataService::class)->version());
    }

    public function test_requests_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get('/api/reference-data')->assertOk();
        }

        $this->get('/api/reference-data')->assertTooManyRequests();
    }

    public function test_export_command_writes_the_served_document(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'reference-data');

        $this->artisan('szentiras:export-reference-data', ['--path' => $path])->assertSuccessful();

        $this->assertSame(app(ReferenceDataService::class)->json(), file_get_contents($path));
        unlink($path);
    }
}
