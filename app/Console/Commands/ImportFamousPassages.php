<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\FamousPassage\FamousPassageService;

/**
 * Creates the famous passages listed in resources/data/famous-passages.json (the ones people
 * search for most, by Search Console) as drafts. Passages that already exist are left as the
 * editors made them; the drafts are published from the editor after review.
 */
class ImportFamousPassages extends Command
{
    private const DATA_FILE = 'data/famous-passages.json';

    protected $signature = 'szentiras:import-famous-passages
        {--file= : JSON file to read instead of resources/data/famous-passages.json}
        {--no-citations : Do not collect the Church documents citing the new passages}';

    protected $description = 'Create the listed famous passages that do not exist yet, as drafts.';

    public function handle(FamousPassageService $famousPassageService): int
    {
        try {
            $entries = $this->entries();
        } catch (JsonException $exception) {
            $this->error("A fájl nem olvasható: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $createdSlugs = [];
        $failed = false;
        $position = (int) FamousPassage::query()->max('position');
        foreach ($entries as $entry) {
            if (FamousPassage::query()->where('slug', $entry['slug'])->exists()) {
                $this->line("{$entry['slug']}: már létezik, kihagyva.");

                continue;
            }
            $location = $famousPassageService->locate($entry['reference']);
            if ($location === null || ($entry['key_verse'] !== null && $famousPassageService->locate($entry['key_verse']) === null)) {
                $this->error("{$entry['slug']}: a hely nem található az alapértelmezett fordításban ({$entry['reference']}, {$entry['key_verse']}).");
                $failed = true;

                continue;
            }

            FamousPassage::query()->create([
                ...$entry,
                ...$location,
                'is_active' => false,
                'position' => ++$position,
            ]);
            $createdSlugs[] = $entry['slug'];
            $this->info("{$entry['slug']}: létrehozva ({$entry['reference']}), vázlatként.");
        }

        if ($createdSlugs !== [] && ! $this->option('no-citations')) {
            $this->call('szentiras:famous-passage-citations', ['slugs' => $createdSlugs]);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{slug: string, name: string, alternative_names: list<string>, reference: string, key_verse: string|null}>
     *
     * @throws JsonException
     */
    private function entries(): array
    {
        $file = $this->option('file') ?? resource_path(self::DATA_FILE);
        $entries = json_decode((string) @file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return array_map(fn (array $entry): array => [
            'slug' => (string) $entry['slug'],
            'name' => (string) $entry['name'],
            'alternative_names' => array_values(array_map('strval', $entry['alternative_names'] ?? [])),
            'reference' => (string) $entry['reference'],
            'key_verse' => isset($entry['key_verse']) ? (string) $entry['key_verse'] : null,
        ], (array) $entries);
    }
}
