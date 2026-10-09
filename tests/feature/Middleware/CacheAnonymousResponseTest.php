<?php

namespace SzentirasHu\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;
use SzentirasHu\Http\Middleware\CacheAnonymousResponse;
use SzentirasHu\Service\Cdn\CacheTagCollector;
use SzentirasHu\Test\Common\TestCase;

class CacheAnonymousResponseTest extends TestCase
{
    private function handle(Request $request, Response $response): Response
    {
        return $this->app->make(CacheAnonymousResponse::class)->handle($request, fn () => $response);
    }

    private function cacheableGet(): Request
    {
        $request = Request::create('/SZIT/Lk1', 'GET');
        $request->attributes->set('cacheable', true);

        return $request;
    }

    private function responseWithCookie(): Response
    {
        $response = new Response('ok', 200);
        $response->headers->setCookie(Cookie::create('laravel_session', 'abc'));

        return $response;
    }

    public function test_anonymous_cacheable_get_is_public_and_cookie_free(): void
    {
        $result = $this->handle($this->cacheableGet(), $this->responseWithCookie());

        $cacheControl = $result->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('s-maxage=' . config('page_cache.cdn_max_age'), $cacheControl);
        $this->assertStringContainsString('max-age=' . config('page_cache.browser_max_age'), $cacheControl);
        $this->assertEmpty($result->headers->getCookies());
        $this->assertNull($result->headers->get('Set-Cookie'));
    }

    public function test_anonymous_cacheable_get_carries_collected_cache_tags(): void
    {
        $this->app->make(CacheTagCollector::class)->add('text-szit-luk');
        $this->app->make(CacheTagCollector::class)->add('text-szit');

        $result = $this->handle($this->cacheableGet(), new Response('ok', 200));

        $this->assertSame('text-szit-luk,text-szit', $result->headers->get('Cache-Tag'));
    }

    public function test_private_response_carries_no_cache_tags(): void
    {
        $this->app->make(CacheTagCollector::class)->add('text-szit-luk');
        $request = $this->cacheableGet();
        $request->cookies->set('anonymous_token', 'some-token');

        $result = $this->handle($request, new Response('ok', 200));

        $this->assertNull($result->headers->get('Cache-Tag'));
    }

    public function test_visitor_with_anonymous_token_gets_private_response(): void
    {
        $request = $this->cacheableGet();
        $request->cookies->set('anonymous_token', 'some-token');

        $result = $this->handle($request, new Response('ok', 200));

        $cacheControl = $result->headers->get('Cache-Control');
        // Logged-in users (anonymous_token) must never be served a shared-cached copy,
        // so they see their own commentaries the moment they generate them.
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_short_ttl_override_is_respected(): void
    {
        $request = $this->cacheableGet();
        $request->attributes->set('cache_max_age', 300);
        $request->attributes->set('cache_cdn_max_age', 300);

        $result = $this->handle($request, new Response('ok', 200));

        $cacheControl = $result->headers->get('Cache-Control');
        $this->assertStringContainsString('s-maxage=300', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
    }

    public function test_non_cacheable_route_is_untouched(): void
    {
        $request = Request::create('/SZIT/Lk1', 'GET');

        $result = $this->handle($request, $this->responseWithCookie());

        $this->assertNotEmpty($result->headers->getCookies());
        $this->assertStringNotContainsString('public', (string) $result->headers->get('Cache-Control'));
    }

    public function test_non_get_request_is_not_publicly_cached(): void
    {
        $request = Request::create('/SZIT/Lk1', 'POST');
        $request->attributes->set('cacheable', true);

        $result = $this->handle($request, $this->responseWithCookie());

        $this->assertNotEmpty($result->headers->getCookies());
        $this->assertStringNotContainsString('public', (string) $result->headers->get('Cache-Control'));
    }
}
