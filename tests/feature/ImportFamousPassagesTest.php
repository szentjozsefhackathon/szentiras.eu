<?php

namespace SzentirasHu\Test;

use Illuminate\Support\Facades\Http;
use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class ImportFamousPassagesTest extends FastDatabaseTestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'famous-passages');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_new_passages_are_created_as_drafts_and_existing_ones_are_kept(): void
    {
        FamousPassage::factory()->create(['name' => 'Az editor neve', 'position' => 5]);
        $this->writeEntries([
            ['slug' => 'a-hetedik-nap', 'name' => 'Felülírná', 'alternative_names' => [], 'reference' => 'Ter 2', 'key_verse' => null],
            ['slug' => 'isten-megaldja', 'name' => 'Isten megáldja a hetedik napot', 'alternative_names' => ['Megszentelt nap'], 'reference' => 'Ter 2,3', 'key_verse' => 'Ter 2,3'],
        ]);

        $this->artisan('szentiras:import-famous-passages', ['--file' => $this->file, '--no-citations' => true])
            ->expectsOutput('a-hetedik-nap: már létezik, kihagyva.')
            ->expectsOutput('isten-megaldja: létrehozva (Ter 2,3), vázlatként.')
            ->assertSuccessful();

        $this->assertSame('Az editor neve', FamousPassage::query()->where('slug', 'a-hetedik-nap')->value('name'));
        $created = FamousPassage::query()->where('slug', 'isten-megaldja')->firstOrFail();
        $this->assertFalse($created->is_active);
        $this->assertSame(['Megszentelt nap'], $created->alternative_names);
        $this->assertSame(['GEN', 2, 2], [$created->usx_code, $created->chapter_from, $created->chapter_to]);
        $this->assertSame(6, $created->position);
    }

    public function test_reference_without_text_is_reported_and_not_created(): void
    {
        $this->writeEntries([
            ['slug' => 'nincs-ilyen', 'name' => 'Nincs ilyen', 'alternative_names' => [], 'reference' => 'Ter 99', 'key_verse' => null],
        ]);

        $this->artisan('szentiras:import-famous-passages', ['--file' => $this->file, '--no-citations' => true])
            ->assertFailed();

        $this->assertFalse(FamousPassage::query()->where('slug', 'nincs-ilyen')->exists());
    }

    public function test_citations_are_collected_for_the_new_passages(): void
    {
        config(['services.magister.url' => 'https://magister.test']);
        Http::fake(['https://magister.test/*' => Http::response(['question' => 'Ter 2,3', 'units' => []])]);
        $this->writeEntries([
            ['slug' => 'isten-megaldja', 'name' => 'Isten megáldja a hetedik napot', 'reference' => 'Ter 2,3'],
        ]);

        $this->artisan('szentiras:import-famous-passages', ['--file' => $this->file])
            ->expectsOutput('isten-megaldja (Ter 2,3): nincs idézet')
            ->assertSuccessful();

        $this->assertSame([], FamousPassage::query()->where('slug', 'isten-megaldja')->firstOrFail()->church_citations);
    }

    public function test_the_shipped_list_is_valid(): void
    {
        $entries = json_decode(file_get_contents(resource_path('data/famous-passages.json')), true, flags: JSON_THROW_ON_ERROR);

        $slugs = array_column($entries, 'slug');
        $this->assertSame($slugs, array_unique($slugs));
        foreach ($entries as $entry) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $entry['slug']);
            $this->assertNotEmpty($entry['name']);
            $this->assertNotEmpty($entry['reference']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function writeEntries(array $entries): void
    {
        file_put_contents($this->file, json_encode($entries, JSON_UNESCAPED_UNICODE));
    }
}
