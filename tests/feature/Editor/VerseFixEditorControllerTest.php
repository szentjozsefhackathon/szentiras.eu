<?php

namespace SzentirasHu\Test\Editor;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\Enum\VerseFixStatus;
use SzentirasHu\Models\VerseFix;
use SzentirasHu\Models\VerseFixRevision;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\VerseFixService;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class VerseFixEditorControllerTest extends FastDatabaseTestCase
{
    private const SEEDED_GEPI = '100100200300';

    private const SEEDED_TEXT = 'verse 100100200300';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(EditorService::class, function ($mock): void {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
            $mock->shouldReceive('isEditor')->andReturn(true);
        });

        Verse::query()->where('trans', 1001)->where('gepi', self::SEEDED_GEPI)->where('book_id', '!=', 99101)->delete();
    }

    private function seededVerse(): Verse
    {
        return Verse::query()->where('trans', 1001)->where('gepi', self::SEEDED_GEPI)->orderBy('id')->firstOrFail();
    }

    private function addVerseRecord(Verse $sibling, int $tip, string $text): Verse
    {
        $verse = $sibling->replicate();
        $verse->tip = $tip;
        $verse->verse = $text;
        $verse->save();

        return $verse;
    }

    private function storedTexts(int $tip = 6): array
    {
        return Verse::query()
            ->where('trans', 1001)
            ->where('gepi', self::SEEDED_GEPI)
            ->where('tip', $tip)
            ->orderBy('id')
            ->pluck('verse')
            ->all();
    }

    private function createFix(string $fixedText = 'verse javított'): VerseFix
    {
        $this->post(route('editor.verseFixes.store'), [
            'verse_id' => $this->seededVerse()->id,
            'fixed_text' => $fixedText,
            'note' => 'https://github.com/example/issues/1',
        ])->assertSessionHasNoErrors();

        return VerseFix::query()->latest('id')->firstOrFail();
    }

    public function test_non_editor_cannot_open_the_editor(): void
    {
        $this->mock(EditorService::class, function ($mock): void {
            $mock->shouldReceive('currentIsEditor')->andReturn(false);
        });

        $this->get(route('editor.verseFixes.index'))->assertForbidden();
        $this->post(route('editor.verseFixes.store'), [])->assertForbidden();
    }

    public function test_index_lists_fixes_and_looks_up_verse_records(): void
    {
        $verseFix = VerseFix::factory()->create([
            'gepi' => 'GEN_7_7',
            'chapter' => 7,
            'numv' => 7,
            'fixed_text' => 'Egy listázott javítás',
        ]);

        $response = $this->get(route('editor.verseFixes.index', ['translation_id' => 1001, 'reference' => 'Ter 2,3']));

        $response->assertOk();
        $response->assertSee('Egy listázott javítás');
        $response->assertSee(route('editor.verseFixes.edit', $verseFix->id), false);
        $response->assertSee(self::SEEDED_TEXT);
        $response->assertSee(route('editor.verseFixes.create', ['verse_id' => $this->seededVerse()->id]), false);
    }

    public function test_index_reports_unparsable_reference(): void
    {
        $this->get(route('editor.verseFixes.index', ['translation_id' => 1001, 'reference' => '!!!']))
            ->assertOk()
            ->assertSee('Nem értelmezhető hivatkozás.');
    }

    public function test_index_filters_by_status(): void
    {
        VerseFix::factory()->create(['fixed_text' => 'Aktív javítás szövege']);
        VerseFix::factory()->withStatus(VerseFixStatus::Conflict)->create(['fixed_text' => 'Ütköző javítás szövege']);

        $this->get(route('editor.verseFixes.index', ['status' => 'conflict']))
            ->assertOk()
            ->assertSee('Ütköző javítás szövege')
            ->assertDontSee('Aktív javítás szövege');
    }

    public function test_create_shows_form_prefilled_with_current_text(): void
    {
        $this->get(route('editor.verseFixes.create', ['verse_id' => $this->seededVerse()->id]))
            ->assertOk()
            ->assertSee('Ter 2,3')
            ->assertSee(self::SEEDED_TEXT);
    }

    public function test_create_redirects_to_existing_fix(): void
    {
        $verseFix = $this->createFix();

        $this->get(route('editor.verseFixes.create', ['verse_id' => $this->seededVerse()->id]))
            ->assertRedirect(route('editor.verseFixes.edit', $verseFix));
    }

    public function test_store_applies_fix_to_every_matching_record_and_records_history(): void
    {
        $duplicate = $this->addVerseRecord($this->seededVerse(), 6, self::SEEDED_TEXT);
        $this->addVerseRecord($this->seededVerse(), 6, 'másik bekezdés');
        $footnote = $this->addVerseRecord($this->seededVerse(), 2001, self::SEEDED_TEXT);

        $response = $this->post(route('editor.verseFixes.store'), [
            'verse_id' => $duplicate->id,
            'fixed_text' => "verse\r\njavított",
            'note' => 'GitHub #12',
        ]);

        $verseFix = VerseFix::query()->firstOrFail();
        $response->assertRedirect(route('editor.verseFixes.edit', $verseFix));
        $response->assertSessionHas('success');

        $this->assertSame(VerseFixStatus::Active, $verseFix->status);
        $this->assertSame(self::SEEDED_TEXT, $verseFix->original_text);
        $this->assertSame("verse\njavított", $verseFix->fixed_text);
        $this->assertSame(["verse\njavított", "verse\njavított", 'másik bekezdés'], $this->storedTexts());
        $this->assertSame(self::SEEDED_TEXT, $footnote->fresh()->verse);

        $revision = $verseFix->revisions()->firstOrFail();
        $this->assertSame(VerseFixRevision::SOURCE_EDITOR, $revision->source);
        $this->assertSame('GitHub #12', $revision->note);
    }

    public function test_store_refreshes_cached_text_but_keeps_unrelated_cache(): void
    {
        $textService = app(TextService::class);
        $translation = Translation::query()->findOrFail(1001);
        $this->assertSame(self::SEEDED_TEXT, $textService->getPureText('Ter 2,3', $translation));
        Cache::forever('unrelated_entry', 'kept');

        $this->createFix();

        $this->assertSame('verse javított', $textService->getPureText('Ter 2,3', $translation));
        $this->assertSame('kept', Cache::get('unrelated_entry'));
    }

    public function test_store_purges_only_the_fixed_book_from_cdn(): void
    {
        config([
            'services.cloudflare.zone_id' => 'zone123',
            'services.cloudflare.api_token' => 'token456',
        ]);
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->createFix();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['tags'] === ['text-testtrans-gen', 'text-testtrans']);
    }

    public function test_text_page_is_tagged_with_its_book(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertHeader('Cache-Tag', 'text-testtrans-gen');
    }

    public function test_store_rejects_unchanged_text(): void
    {
        $this->post(route('editor.verseFixes.store'), [
            'verse_id' => $this->seededVerse()->id,
            'fixed_text' => self::SEEDED_TEXT,
        ])->assertSessionHasErrors('fixed_text');

        $this->assertSame(0, VerseFix::query()->count());
    }

    public function test_store_requires_fixed_text(): void
    {
        $this->post(route('editor.verseFixes.store'), [
            'verse_id' => $this->seededVerse()->id,
            'fixed_text' => '',
        ])->assertSessionHasErrors('fixed_text');
    }

    public function test_store_rejects_second_fix_for_same_record(): void
    {
        $this->createFix();

        $this->post(route('editor.verseFixes.store'), [
            'verse_id' => $this->seededVerse()->id,
            'fixed_text' => 'még egy javítás',
        ])->assertSessionHasErrors('fixed_text');

        $this->assertSame(1, VerseFix::query()->count());
    }

    public function test_edit_shows_history(): void
    {
        $verseFix = $this->createFix('első változat');
        $this->put(route('editor.verseFixes.update', $verseFix), ['fixed_text' => 'második változat']);

        $this->get(route('editor.verseFixes.edit', $verseFix))
            ->assertOk()
            ->assertSee('Előzmények')
            ->assertSeeInOrder(['második változat', 'első változat']);
    }

    public function test_update_saves_new_version(): void
    {
        $verseFix = $this->createFix('első változat');

        $this->put(route('editor.verseFixes.update', $verseFix), [
            'fixed_text' => 'második változat',
            'note' => 'pontosítva',
        ])->assertRedirect(route('editor.verseFixes.edit', $verseFix));

        $verseFix->refresh();
        $this->assertSame('második változat', $verseFix->fixed_text);
        $this->assertSame(self::SEEDED_TEXT, $verseFix->original_text);
        $this->assertSame(['második változat'], $this->storedTexts());
        $this->assertSame(['második változat', 'első változat'], $verseFix->revisions->pluck('fixed_text')->all());
    }

    public function test_update_reenables_disabled_fix(): void
    {
        $verseFix = $this->createFix('első változat');
        $this->post(route('editor.verseFixes.disable', $verseFix));

        $this->put(route('editor.verseFixes.update', $verseFix), ['fixed_text' => 'újra bekapcsolva'])
            ->assertSessionHasNoErrors();

        $this->assertSame(VerseFixStatus::Active, $verseFix->fresh()->status);
        $this->assertSame(['újra bekapcsolva'], $this->storedTexts());
    }

    public function test_update_fails_when_neither_text_is_stored_anymore(): void
    {
        $verseFix = $this->createFix('első változat');
        $verseFix->update(['status' => VerseFixStatus::Conflict]);
        $this->seededVerse()->update(['verse' => 'a kiadó új szövege']);

        $this->put(route('editor.verseFixes.update', $verseFix), ['fixed_text' => 'második változat'])
            ->assertSessionHasErrors('fixed_text');

        $this->assertSame('első változat', $verseFix->fresh()->fixed_text);
        $this->assertSame(['a kiadó új szövege'], $this->storedTexts());
    }

    public function test_update_rebases_conflicting_fix_on_chosen_record(): void
    {
        $verseFix = $this->createFix('első változat');
        $verseFix->update(['status' => VerseFixStatus::Conflict]);
        $verse = $this->seededVerse();
        $verse->update(['verse' => 'a kiadó új szövege']);

        $this->put(route('editor.verseFixes.update', $verseFix), [
            'fixed_text' => 'a kiadó új szövege, javítva',
            'base_verse_id' => $verse->id,
        ])->assertSessionHasNoErrors();

        $verseFix->refresh();
        $this->assertSame(VerseFixStatus::Active, $verseFix->status);
        $this->assertSame('a kiadó új szövege', $verseFix->original_text);
        $this->assertSame(['a kiadó új szövege, javítva'], $this->storedTexts());
    }

    public function test_update_rejects_base_record_of_another_verse(): void
    {
        $verseFix = $this->createFix();
        $otherVerse = Verse::query()->where('trans', 1001)->where('gepi', '!=', self::SEEDED_GEPI)->firstOrFail();

        $this->put(route('editor.verseFixes.update', $verseFix), [
            'fixed_text' => 'bármi',
            'base_verse_id' => $otherVerse->id,
        ])->assertSessionHasErrors('base_verse_id');
    }

    public function test_disable_restores_original_text(): void
    {
        $verseFix = $this->createFix();

        $this->post(route('editor.verseFixes.disable', $verseFix))
            ->assertRedirect(route('editor.verseFixes.edit', $verseFix));

        $verseFix->refresh();
        $this->assertSame(VerseFixStatus::Disabled, $verseFix->status);
        $this->assertSame([self::SEEDED_TEXT], $this->storedTexts());
        $this->assertSame(2, $verseFix->revisions()->count());
    }

    public function test_reapply_after_import_applies_fix_again(): void
    {
        $verseFix = $this->createFix();
        $this->seededVerse()->update(['verse' => self::SEEDED_TEXT]);

        $statusCounts = app(VerseFixService::class)->reapplyForTranslation(1001);

        $this->assertSame(1, $statusCounts['active']);
        $this->assertSame(['verse javított'], $this->storedTexts());
        $this->assertSame(1, $verseFix->revisions()->count());
    }

    public function test_reapply_detects_fix_already_in_source(): void
    {
        $verseFix = $this->createFix();

        $statusCounts = app(VerseFixService::class)->reapplyForTranslation(1001);

        $this->assertSame(1, $statusCounts['resolved_upstream']);
        $verseFix->refresh();
        $this->assertSame(VerseFixStatus::ResolvedUpstream, $verseFix->status);
        $this->assertSame(VerseFixRevision::SOURCE_IMPORT, $verseFix->revisions()->first()->source);
    }

    public function test_reapply_marks_conflict_when_source_changed_otherwise(): void
    {
        $verseFix = $this->createFix();
        $this->seededVerse()->update(['verse' => 'a kiadó új szövege']);

        $statusCounts = app(VerseFixService::class)->reapplyForTranslation(1001);

        $this->assertSame(1, $statusCounts['conflict']);
        $this->assertSame(VerseFixStatus::Conflict, $verseFix->fresh()->status);
        $this->assertSame(['a kiadó új szövege'], $this->storedTexts());
    }

    public function test_reapply_resolves_earlier_conflict_when_original_returns(): void
    {
        $verseFix = $this->createFix();
        $verseFix->update(['status' => VerseFixStatus::Conflict]);
        $this->seededVerse()->update(['verse' => self::SEEDED_TEXT]);

        app(VerseFixService::class)->reapplyForTranslation(1001);

        $this->assertSame(VerseFixStatus::Active, $verseFix->fresh()->status);
        $this->assertSame(['verse javított'], $this->storedTexts());
    }

    public function test_reapply_skips_disabled_fixes(): void
    {
        $verseFix = $this->createFix();
        $this->post(route('editor.verseFixes.disable', $verseFix));

        app(VerseFixService::class)->reapplyForTranslation(1001);

        $this->assertSame(VerseFixStatus::Disabled, $verseFix->fresh()->status);
        $this->assertSame([self::SEEDED_TEXT], $this->storedTexts());
    }
}
