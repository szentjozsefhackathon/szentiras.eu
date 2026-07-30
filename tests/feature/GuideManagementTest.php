<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SzentirasHu\Models\Guide;
use SzentirasHu\Models\Tag;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Test\Common\TestCase;

class GuideManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(EditorService::class, function ($mock): void {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
        });
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    public function test_public_guide_list_only_shows_active_guides_in_configured_order(): void
    {
        $secondGuide = Guide::factory()->create([
            'title' => 'Második útmutató',
            'slug' => 'masodik-utmutato',
            'position' => 2,
        ]);
        $firstGuide = Guide::factory()->create([
            'title' => 'Első útmutató',
            'slug' => 'elso-utmutato',
            'position' => 1,
        ]);
        Guide::factory()->inactive()->create([
            'title' => 'Rejtett útmutató',
            'slug' => 'rejtett-utmutato',
            'position' => 0,
        ]);
        $guideTag = Tag::factory()->create([
            'name' => 'Útmutató',
            'slug' => 'utmutato',
        ]);
        $firstGuide->tags()->attach($guideTag);

        $response = $this->get(route('guides.index'));

        $response->assertOk();
        $response->assertSeeInOrder([$firstGuide->title, $secondGuide->title]);
        $response->assertSee('<span class="badge rounded-pill bg-secondary">Útmutató</span>', false);
        $response->assertDontSee('Rejtett útmutató');
    }

    public function test_active_guide_renders_markdown_and_inactive_guide_is_not_public(): void
    {
        $activeGuide = Guide::factory()->create([
            'title' => 'Keresési útmutató',
            'slug' => 'keresesi-utmutato',
            'content' => "## Első lépések\n\nHasználd a **gyorskeresőt**.",
        ]);
        $inactiveGuide = Guide::factory()->inactive()->create([
            'slug' => 'kesobbi-utmutato',
        ]);

        $this->get(route('guides.show', $activeGuide))
            ->assertOk()
            ->assertSee('<h2>Első lépések</h2>', false)
            ->assertSee('<strong>gyorskeresőt</strong>', false);

        $this->get(route('guides.show', $inactiveGuide))->assertNotFound();
    }

    public function test_guide_sitemap_is_lightweight_and_only_lists_active_guides(): void
    {
        $activeGuide = Guide::factory()->create([
            'slug' => 'aktiv-cikk',
        ]);
        $inactiveGuide = Guide::factory()->inactive()->create([
            'slug' => 'inaktiv-cikk',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get(route('sitemaps.guides'));

        DB::disableQueryLog();
        $queries = DB::getQueryLog();

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertHeader('Cache-Control');
        $response->assertHeader('ETag');
        $response->assertSee('<loc>'.route('guides.show', $activeGuide).'</loc>', false);
        $response->assertDontSee('<loc>'.route('guides.show', $inactiveGuide).'</loc>', false);

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('guides', $queries[0]['query']);
        $this->assertStringNotContainsString('books', $queries[0]['query']);
        $this->assertStringNotContainsString('verses', $queries[0]['query']);
    }

    public function test_information_navigation_links_to_guides(): void
    {
        $this->get('/informaciok')
            ->assertOk()
            ->assertSee(route('guides.index'), false)
            ->assertSee('Útmutatók/Cikkek');
    }

    public function test_non_editor_cannot_manage_guides(): void
    {
        $this->mock(EditorService::class, function ($mock): void {
            $mock->shouldReceive('currentIsEditor')->andReturn(false);
        });

        $this->post(route('editor.guides.store'), [
            'title' => 'Jogosulatlan útmutató',
            'content' => 'Nem menthető.',
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('guides', [
            'title' => 'Jogosulatlan útmutató',
        ]);
    }

    public function test_editor_management_pages_render(): void
    {
        $guide = Guide::factory()->create();

        $this->get(route('editor.guides.index'))
            ->assertOk()
            ->assertSee($guide->title);
        $this->get(route('editor.guides.create'))
            ->assertOk()
            ->assertSee('Új útmutató/cikk')
            ->assertSee('name="tags"', false);
        $this->get(route('editor.guides.edit', $guide))
            ->assertOk()
            ->assertSee($guide->content);
    }

    public function test_editor_can_create_update_and_deactivate_guide(): void
    {
        $createResponse = $this->post(route('editor.guides.store'), [
            'title' => 'A kereső használata',
            'content' => '## Keresés',
            'tags' => 'Útmutató, Bibliaolvasás, útmutató',
            'is_active' => true,
        ]);

        $guide = Guide::query()->where('title', 'A kereső használata')->firstOrFail();
        $createResponse->assertRedirect(route('editor.guides.edit', $guide));
        $this->assertSame('a-kereso-hasznalata', $guide->slug);
        $this->assertTrue($guide->is_active);
        $this->assertSame(
            ['Bibliaolvasás', 'Útmutató'],
            $guide->tags()->orderBy('name')->pluck('name')->all(),
        );

        $this->put(route('editor.guides.update', $guide), [
            'title' => 'A gyorskereső használata',
            'content' => "## Gyorskeresés\n\nÚj tartalom.",
            'tags' => 'Cikk',
            'is_active' => true,
        ])->assertRedirect(route('editor.guides.edit', $guide));

        $guide->refresh();
        $this->assertSame('A gyorskereső használata', $guide->title);
        $this->assertSame('a-kereso-hasznalata', $guide->slug);
        $this->assertSame(['Cikk'], $guide->tags()->pluck('name')->all());

        $this->patch(route('editor.guides.toggle', $guide))
            ->assertRedirect(route('editor.guides.index'));

        $this->assertFalse($guide->refresh()->is_active);
    }

    public function test_editor_can_delete_guide(): void
    {
        $guide = Guide::factory()->create();

        $this->delete(route('editor.guides.destroy', $guide))
            ->assertRedirect(route('editor.guides.index'));

        $this->assertDatabaseMissing('guides', [
            'id' => $guide->id,
        ]);
    }

    public function test_editor_can_reorder_all_guides(): void
    {
        $firstGuide = Guide::factory()->create(['position' => 1]);
        $secondGuide = Guide::factory()->create(['position' => 2]);
        $thirdGuide = Guide::factory()->create(['position' => 3]);

        $this->patchJson(route('editor.guides.reorder'), [
            'guides' => [$thirdGuide->id, $firstGuide->id, $secondGuide->id],
        ])->assertOk();

        $this->assertSame(
            [$thirdGuide->id, $firstGuide->id, $secondGuide->id],
            Guide::query()->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_reorder_rejects_an_incomplete_guide_list(): void
    {
        $firstGuide = Guide::factory()->create(['position' => 1]);
        Guide::factory()->create(['position' => 2]);

        $this->patchJson(route('editor.guides.reorder'), [
            'guides' => [$firstGuide->id],
        ])->assertUnprocessable();
    }

    public function test_guide_validation_requires_title_and_content(): void
    {
        $this->post(route('editor.guides.store'), [
            'title' => '',
            'content' => '',
        ])->assertSessionHasErrors(['title', 'content']);
    }

    public function test_guide_validation_rejects_too_many_or_invalid_tags(): void
    {
        $this->post(route('editor.guides.store'), [
            'title' => 'Túl sok címke',
            'content' => 'Tartalom',
            'tags' => implode(', ', range(1, 11)),
        ])->assertSessionHasErrors('tags');

        $this->post(route('editor.guides.store'), [
            'title' => 'Érvénytelen címke',
            'content' => 'Tartalom',
            'tags' => '📖',
        ])->assertSessionHasErrors('tags');
    }
}
