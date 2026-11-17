<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Models\BookIntroduction;
use SzentirasHu\Service\BookIntroduction\BookIntroductionXmlParser;

/**
 * Imports the book and book-group introductions of a translation from its XML
 * source, replacing the previously imported ones. Independent of the text
 * import, so it can run without taking the site down.
 */
class ImportBookIntroductions extends Command
{
    protected $signature = 'szentiras:import-book-introductions
        {translation : Translation abbreviation, e.g. SZIT, KNB, BD}
        {--file= : Local XML file to read instead of xml/{TRANSLATION}.xml on S3}
        {--no-purge : Do not purge the CDN cache afterwards}';

    protected $description = 'Import the book introductions of a translation from its XML source.';

    public function __construct(
        private readonly BookIntroductionXmlParser $parser,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $translationAbbrev = strtoupper((string) $this->argument('translation'));
        $translation = Translation::query()->where('abbrev', $translationAbbrev)->first();
        if ($translation === null) {
            $this->error("Ismeretlen fordítás: {$translationAbbrev}");

            return self::FAILURE;
        }

        try {
            $introductions = $this->parser->parse($this->readSource($translationAbbrev));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        DB::transaction(function () use ($translation, $introductions): void {
            BookIntroduction::query()->where('translation_id', $translation->id)->delete();
            foreach ($introductions as $introduction) {
                BookIntroduction::query()->create([...$introduction, 'translation_id' => $translation->id]);
            }
        });

        $groupCount = count(array_filter($introductions, fn (array $introduction): bool => $introduction['is_group']));
        $coveredBooks = array_unique(array_merge([], ...array_column($introductions, 'usx_codes')));
        $this->info(sprintf(
            '%s: %d bevezető betöltve (%d könyv-, %d csoportbevezető), %d könyvet érint.',
            $translationAbbrev,
            count($introductions),
            count($introductions) - $groupCount,
            $groupCount,
            count($coveredBooks),
        ));

        if (! $this->option('no-purge')) {
            $this->call('cdn:purge');
        }

        return self::SUCCESS;
    }

    private function readSource(string $translationAbbrev): string
    {
        $file = $this->option('file');
        if ($file !== null) {
            $content = is_readable($file) ? file_get_contents($file) : false;
            if ($content === false) {
                throw new RuntimeException("A fájl nem olvasható: {$file}");
            }

            return $content;
        }

        $path = "xml/{$translationAbbrev}.xml";
        $this->info("Letöltés: s3://{$path}");
        $content = Storage::disk('s3')->get($path);
        if ($content === null) {
            throw new RuntimeException("Nincs ilyen forrásfájl az S3-on: {$path}");
        }

        return $content;
    }
}
