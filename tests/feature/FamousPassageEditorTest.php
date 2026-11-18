<?php

namespace SzentirasHu\Test;

use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\Cdn\CloudflareCacheService;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class FamousPassageEditorTest extends FastDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(EditorService::class, function ($mock): void {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
        });
        $this->mock(CloudflareCacheService::class, function ($mock): void {
            $mock->shouldReceive('purgeTags')->andReturn(true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return [
            'name' => 'A hetedik nap',
            'alternative_names' => "Isten megpihen\r\n\r\n  A szombat  ",
            'reference' => 'Ter 2',
            'key_verse' => 'Ter 2,3',
            'introduction' => 'Bevezető.',
            'content' => '## Összefüggés',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_editor_creates_a_passage_with_its_book_and_chapters_derived_from_the_reference(): void
    {
        $this->post(route('editor.famousPassages.store'), $this->validInput())
            ->assertRedirect(route('editor.famousPassages.edit', 'a-hetedik-nap'));

        $passage = FamousPassage::query()->where('slug', 'a-hetedik-nap')->firstOrFail();
        $this->assertSame(['Isten megpihen', 'A szombat'], $passage->alternative_names);
        $this->assertSame('GEN', $passage->usx_code);
        $this->assertSame(2, $passage->chapter_from);
        $this->assertSame(2, $passage->chapter_to);
        $this->assertTrue($passage->is_active);
    }

    public function test_saving_purges_the_cached_chapter_pages_of_the_book_in_every_translation(): void
    {
        $this->mock(CloudflareCacheService::class, function ($mock): void {
            $mock->shouldReceive('purgeTags')->once()->withArgs(
                fn (array $tags): bool => in_array('text-testtrans-gen', $tags, true) && in_array('text-testtrans2-gen', $tags, true)
            )->andReturn(true);
        });

        $this->post(route('editor.famousPassages.store'), $this->validInput())->assertRedirect();
    }

    public function test_generated_slug_does_not_collide_with_an_existing_one(): void
    {
        FamousPassage::factory()->create();

        $this->post(route('editor.famousPassages.store'), $this->validInput())->assertRedirect();

        $this->assertTrue(FamousPassage::query()->where('slug', 'a-hetedik-nap-2')->exists());
    }

    public function test_reference_must_have_text_in_the_default_translation(): void
    {
        foreach (['Ter 7', 'nincs ilyen', 'Ter 2; Kiv 1'] as $reference) {
            $this->post(route('editor.famousPassages.store'), $this->validInput(['reference' => $reference]))
                ->assertSessionHasErrors('reference');
        }
        $this->post(route('editor.famousPassages.store'), $this->validInput(['key_verse' => 'Ter 9,9']))
            ->assertSessionHasErrors('key_verse');

        $this->assertSame(0, FamousPassage::query()->count());
    }

    public function test_slug_must_be_url_friendly_and_unique(): void
    {
        FamousPassage::factory()->create();

        $this->post(route('editor.famousPassages.store'), $this->validInput(['slug' => 'Nagy Betű']))
            ->assertSessionHasErrors('slug');
        $this->post(route('editor.famousPassages.store'), $this->validInput(['slug' => 'a-hetedik-nap']))
            ->assertSessionHasErrors('slug');
    }

    public function test_update_recomputes_the_chapters_and_keeps_the_slug(): void
    {
        $passage = FamousPassage::factory()->create();

        $this->put(route('editor.famousPassages.update', $passage), $this->validInput([
            'name' => 'Teremtés vége',
            'reference' => 'Ter 2,3-50,9',
            'is_active' => '0',
        ]))->assertRedirect(route('editor.famousPassages.edit', 'a-hetedik-nap'));

        $passage->refresh();
        $this->assertSame('Teremtés vége', $passage->name);
        $this->assertSame('a-hetedik-nap', $passage->slug);
        $this->assertSame(2, $passage->chapter_from);
        $this->assertSame(50, $passage->chapter_to);
        $this->assertFalse($passage->is_active);
    }

    public function test_editor_pages_render_and_delete_works(): void
    {
        $passage = FamousPassage::factory()->create();

        $this->get(route('editor.famousPassages.index'))->assertOk()->assertSee('A hetedik nap');
        $this->get(route('editor.famousPassages.create'))->assertOk()->assertSee('name="reference"', false);
        $this->get(route('editor.famousPassages.edit', $passage))->assertOk()->assertSee('Isten megpihen');

        $this->delete(route('editor.famousPassages.destroy', $passage))
            ->assertRedirect(route('editor.famousPassages.index'));
        $this->assertModelMissing($passage);
    }

    public function test_non_editor_cannot_manage_passages(): void
    {
        $this->mock(EditorService::class, function ($mock): void {
            $mock->shouldReceive('currentIsEditor')->andReturn(false);
        });

        $this->post(route('editor.famousPassages.store'), $this->validInput())->assertForbidden();
        $this->assertSame(0, FamousPassage::query()->count());
    }
}
