<?php

namespace SzentirasHu\Test\Editor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ApiKey;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Test\Common\TestCase;

class ApiKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(EditorService::class, function ($mock) {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
        });
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    public function testEditorCanEditSelfServiceKey(): void
    {
        $owner = AnonymousId::factory()->create();
        $key = ApiKey::factory()->selfService()->create([
            'name' => 'Felhasználói kulcs',
            'created_by_anonymous_id' => $owner->id,
        ]);

        $response = $this->put(route('editor.apiKeys.update', $key), [
            'name' => 'Szerkesztett kulcs',
            'enabled' => false,
        ]);

        $response->assertRedirect(route('editor.apiKeys.show', $key));

        $key->refresh();
        $this->assertEquals('Szerkesztett kulcs', $key->name);
        $this->assertFalse($key->enabled);
    }

    public function testEditorCanDeleteSelfServiceKey(): void
    {
        $owner = AnonymousId::factory()->create();
        $key = ApiKey::factory()->selfService()->create([
            'created_by_anonymous_id' => $owner->id,
        ]);

        $response = $this->delete(route('editor.apiKeys.destroy', $key));

        $response->assertRedirect(route('editor.apiKeys.index'));
        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
    }
}
