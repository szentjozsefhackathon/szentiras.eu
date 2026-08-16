<?php

namespace SzentirasHu\Test\Editor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: string}>
     */
    public static function keyPages(): array
    {
        return [
            'index' => ['index'],
            'create' => ['create'],
            'show' => ['show'],
            'edit' => ['edit'],
        ];
    }

    #[DataProvider('keyPages')]
    public function testKeyPagesShowAttributionAndLicenceNotice(string $page): void
    {
        $owner = AnonymousId::factory()->create();
        $key = ApiKey::factory()->selfService()->create([
            'created_by_anonymous_id' => $owner->id,
        ]);

        $response = in_array($page, ['show', 'edit'], true)
            ? $this->get(route('editor.apiKeys.'.$page, $key))
            : $this->get(route('editor.apiKeys.'.$page));

        $response->assertOk();
        $response->assertSee('tüntesd fel forrásként a');
        $response->assertSee('href="https://szentiras.eu"', false);
        $response->assertSee('a magyar szerzői jogi törvénynek megfelelően');
        $response->assertSee('CC BY-SA 4.0');
    }
}
