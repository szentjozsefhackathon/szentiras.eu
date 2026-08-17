<?php

namespace SzentirasHu\Test\Mcp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use SzentirasHu\Data\Entity\ApiKey;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * Covers the HTTP transport, which is what remote MCP clients actually use: the tradition
 * is chosen by the URL segment, and the API key may travel as a query parameter because
 * such clients generally allow configuring only a URL.
 */
class BibleMcpEndpointTest extends FastDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Translation::where('id', 1001)->update(['denom' => 'katolikus']);
        Translation::where('id', 1002)->update(['denom' => 'protestáns']);

        config(['settings.enabledTranslations' => [1001, 1002]]);

        Cache::flush();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function callTool(string $uri, string $tool = 'get-verses', array $arguments = ['reference' => 'Ter 2,3'], array $headers = []): TestResponse
    {
        return $this->postJson($uri, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $arguments],
        ], array_merge(['Accept' => 'application/json, text/event-stream'], $headers));
    }

    /**
     * The tool result is a JSON string nested inside the JSON-RPC envelope.
     */
    private function toolText(TestResponse $response): string
    {
        return (string) $response->json('result.content.0.text');
    }

    /**
     * @return array<string, mixed>
     */
    private function toolPayload(TestResponse $response): array
    {
        return json_decode($this->toolText($response), true) ?? [];
    }

    private function createApiKey(string $key = 'mcptest_secret_key', array $attributes = []): ApiKey
    {
        return ApiKey::create(array_merge([
            'name' => 'MCP test key',
            'key_prefix' => substr($key, 0, 8),
            'key_hash' => \Hash::make($key),
            'is_internal' => false,
            'enabled' => true,
            'usage_count' => 0,
        ], $attributes));
    }

    public function test_endpoint_without_translation_segment_uses_the_default(): void
    {
        $response = $this->callTool('/mcp/bible');

        $response->assertOk();
        $this->assertSame('TESTTRANS', $this->toolPayload($response)['translation']['abbrev']);
    }

    public function test_url_segment_selects_the_translation(): void
    {
        $response = $this->callTool('/mcp/bible/TESTTRANS2');

        $response->assertOk();
        $translation = $this->toolPayload($response)['translation'];
        $this->assertSame('TESTTRANS2', $translation['abbrev']);
        $this->assertSame('protestáns', $translation['denomination']);
    }

    public function test_url_segment_is_case_insensitive(): void
    {
        $response = $this->callTool('/mcp/bible/testtrans2');

        $response->assertOk();
        $this->assertSame('TESTTRANS2', $this->toolPayload($response)['translation']['abbrev']);
    }

    public function test_tool_argument_overrides_the_url_segment(): void
    {
        $response = $this->callTool('/mcp/bible/TESTTRANS2', 'get-verses', [
            'reference' => 'Ter 2,3',
            'translation' => 'TESTTRANS',
        ]);

        $response->assertOk();
        $this->assertSame('TESTTRANS', $this->toolPayload($response)['translation']['abbrev']);
    }

    public function test_unknown_translation_segment_is_rejected(): void
    {
        $response = $this->callTool('/mcp/bible/XYZ');

        $response->assertOk();
        $this->assertStringContainsString('Unknown translation', $this->toolText($response));
        $this->assertTrue($response->json('result.isError'));
    }

    public function test_server_instructions_ask_for_attribution_and_state_the_licences(): void
    {
        $response = $this->postJson('/mcp/bible', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ], ['Accept' => 'application/json, text/event-stream']);

        $response->assertOk();
        $instructions = (string) $response->json('result.instructions');
        $this->assertStringContainsString('credit szentiras.eu as the source', $instructions);
        $this->assertStringContainsString('CC BY-SA 4.0', $instructions);
    }

    public function test_api_key_may_be_supplied_as_a_query_parameter(): void
    {
        config(['api.key_required' => true]);
        $this->createApiKey();

        $this->callTool('/mcp/bible?api_key=mcptest_secret_key')->assertOk();
    }

    public function test_api_key_header_still_works(): void
    {
        config(['api.key_required' => true]);
        $this->createApiKey();

        $this->callTool('/mcp/bible', headers: ['X-API-Key' => 'mcptest_secret_key'])->assertOk();
    }

    public function test_request_without_api_key_is_rejected_when_keys_are_required(): void
    {
        config(['api.key_required' => true]);

        $this->callTool('/mcp/bible')->assertStatus(401);
    }

    public function test_invalid_api_key_is_rejected(): void
    {
        config(['api.key_required' => true]);
        $this->createApiKey();

        $this->callTool('/mcp/bible?api_key=wrongkey_nope')->assertStatus(401);
    }

    public function test_query_parameter_key_is_still_rate_limited(): void
    {
        // Re-reading the X-API-Key header inside the rate limiter would find nothing for a
        // query-parameter key and grant unlimited access, so pin the throttling down here.
        config(['api.key_required' => true]);
        $this->createApiKey(attributes: ['throttle_rate' => 2]);

        $this->callTool('/mcp/bible?api_key=mcptest_secret_key')->assertOk();
        $this->callTool('/mcp/bible?api_key=mcptest_secret_key')->assertOk();
        $this->callTool('/mcp/bible?api_key=mcptest_secret_key')->assertStatus(429);
    }

    public function test_api_key_is_not_written_to_the_application_log(): void
    {
        config(['api.key_required' => true]);
        $this->createApiKey();

        \Log::spy();

        $this->callTool('/mcp/bible?api_key=mcptest_secret_key')->assertOk();

        \Log::shouldNotHaveReceived('info', function (string $message, array $context = []): bool {
            return str_contains(json_encode($context) ?: '', 'mcptest_secret_key');
        });
    }
}
