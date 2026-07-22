<?php

namespace SzentirasHu\Test\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ApiKey;
use SzentirasHu\Test\Common\TestCase;

class ApiKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    private function loginAs(AnonymousId $anonymousId): void
    {
        $this->withSession(['anonymous_token' => $anonymousId->token]);
    }

    public function testGuestIsRedirectedToRegister(): void
    {
        $response = $this->get(route('profile.apiKeys.index'));

        $response->assertRedirect('/register');
    }

    public function testUserCanCreateSelfServiceKey(): void
    {
        $anonymousId = AnonymousId::factory()->create();
        $this->loginAs($anonymousId);

        $response = $this->post(route('profile.apiKeys.store'), [
            'name' => 'Saját alkalmazás',
            'description' => 'Teszt leírás',
        ]);

        $apiKey = ApiKey::where('created_by_anonymous_id', $anonymousId->id)->first();

        $this->assertNotNull($apiKey);
        $response->assertRedirect(route('profile.apiKeys.show', $apiKey));

        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
            'name' => 'Saját alkalmazás',
            'is_self_service' => true,
            'is_internal' => false,
            'enabled' => true,
            'created_by_anonymous_id' => $anonymousId->id,
        ]);
        $this->assertNotNull($apiKey->key_plain);
    }

    public function testCreateKeyRequiresName(): void
    {
        $anonymousId = AnonymousId::factory()->create();
        $this->loginAs($anonymousId);

        $response = $this->post(route('profile.apiKeys.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function testIndexOnlyShowsOwnSelfServiceKeys(): void
    {
        $owner = AnonymousId::factory()->create();
        $otherUser = AnonymousId::factory()->create();

        $ownKey = ApiKey::factory()->selfService()->create([
            'name' => 'Sajat kulcs',
            'created_by_anonymous_id' => $owner->id,
        ]);
        $otherKey = ApiKey::factory()->selfService()->create([
            'name' => 'Masik kulcs',
            'created_by_anonymous_id' => $otherUser->id,
        ]);
        $editorMadeKey = ApiKey::factory()->create([
            'name' => 'Editor kulcs',
            'created_by_anonymous_id' => $owner->id,
        ]);

        $this->loginAs($owner);

        $response = $this->get(route('profile.apiKeys.index'));

        $response->assertOk();
        $response->assertSee('Sajat kulcs');
        $response->assertDontSee('Masik kulcs');
        $response->assertDontSee('Editor kulcs');
    }

    public function testUserCanViewOwnKeyWithPlainSecret(): void
    {
        $owner = AnonymousId::factory()->create();
        $key = ApiKey::factory()->selfService()->create([
            'created_by_anonymous_id' => $owner->id,
        ]);

        $this->loginAs($owner);

        $response = $this->get(route('profile.apiKeys.show', $key));

        $response->assertOk();
        $response->assertSee($key->key_plain);
    }

    public function testUserCannotViewOthersKey(): void
    {
        $owner = AnonymousId::factory()->create();
        $otherUser = AnonymousId::factory()->create();
        $key = ApiKey::factory()->selfService()->create([
            'created_by_anonymous_id' => $otherUser->id,
        ]);

        $this->loginAs($owner);

        $response = $this->get(route('profile.apiKeys.show', $key));

        $response->assertForbidden();
    }

    public function testUserCanDeleteOwnKey(): void
    {
        $owner = AnonymousId::factory()->create();
        $key = ApiKey::factory()->selfService()->create([
            'created_by_anonymous_id' => $owner->id,
        ]);

        $this->loginAs($owner);

        $response = $this->delete(route('profile.apiKeys.destroy', $key));

        $response->assertRedirect(route('profile.apiKeys.index'));
        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
    }

    public function testUserCannotDeleteOthersKey(): void
    {
        $owner = AnonymousId::factory()->create();
        $otherUser = AnonymousId::factory()->create();
        $key = ApiKey::factory()->selfService()->create([
            'created_by_anonymous_id' => $otherUser->id,
        ]);

        $this->loginAs($owner);

        $response = $this->delete(route('profile.apiKeys.destroy', $key));

        $response->assertForbidden();
        $this->assertDatabaseHas('api_keys', ['id' => $key->id]);
    }

    public function testKeyLimitIsEnforced(): void
    {
        $owner = AnonymousId::factory()->create();
        ApiKey::factory()->selfService()->count(5)->create([
            'created_by_anonymous_id' => $owner->id,
        ]);

        $this->loginAs($owner);

        $response = $this->post(route('profile.apiKeys.store'), [
            'name' => 'Hatodik kulcs',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertEquals(5, ApiKey::where('created_by_anonymous_id', $owner->id)->count());
    }
}
