<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\GreekVerseAnalysis;
use SzentirasHu\Service\VerseAnalysis\VerseAnalysisValidator;
use Throwable;

class ImportVerseAnalysis extends Command
{
    protected $signature = 'szentiras:import-verse-analysis
        {--disk=local : Laravel filesystem disk containing the chapter JSON files.}
        {--path=greek/verse-analysis/OpenGNT/hu/v1 : Directory containing the chapter JSON files.}
        {--locale=hu : Locale of the imported analysis.}
        {--dry-run : Validate every file without writing to the database.}
        {--prune : Delete previously imported rows below this path that are absent from the current import.}';

    protected $description = 'Validate and import per-chapter Greek verse-analysis JSON files.';

    public function __construct(private readonly VerseAnalysisValidator $validator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $diskName = trim((string) $this->option('disk'));
        $path = trim((string) $this->option('path'), '/');
        $locale = trim((string) $this->option('locale'));

        if ($diskName === '' || $path === '' || preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            $this->error('A disk, a non-empty path, and a locale such as "hu" or "hu-HU" are required.');

            return self::FAILURE;
        }

        try {
            $storage = Storage::disk($diskName);
            $sourceKeys = collect($storage->allFiles($path))
                ->filter(fn (string $sourceKey): bool => Str::endsWith(Str::lower($sourceKey), '.json'))
                ->sort()
                ->values();
        } catch (Throwable $exception) {
            $this->error("Could not list {$diskName}:{$path}: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if ($sourceKeys->isEmpty()) {
            $this->error("No JSON files found below {$diskName}:{$path}.");

            return self::FAILURE;
        }

        $artifacts = [];
        $errorCount = 0;
        $warningCount = 0;

        foreach ($sourceKeys as $sourceKey) {
            try {
                $analysis = $this->decode($this->read($storage, $sourceKey));
                $chapterVerses = $this->chapterVerses($analysis);
                $validation = $this->validator->validate($analysis, $chapterVerses);
                $validation['errors'] = array_merge(
                    $this->filenameErrors($sourceKey, $analysis),
                    $validation['errors']
                );
            } catch (Throwable $exception) {
                $this->line("<error>FAIL</error>  {$sourceKey}");
                $this->line("        {$exception->getMessage()}");
                $errorCount++;

                continue;
            }

            if ($validation['errors'] === []) {
                $this->line("<info>OK</info>    {$sourceKey}".(
                    $validation['warnings'] === []
                        ? ''
                        : ' ('.count($validation['warnings']).' warning(s))'
                ));
                $artifacts[] = [
                    'source_key' => $sourceKey,
                    'analysis' => $analysis,
                ];
            } else {
                $this->line("<error>FAIL</error>  {$sourceKey}");
                $errorCount += count($validation['errors']);
            }

            foreach ($validation['errors'] as $error) {
                $this->line("        {$error}");
            }

            foreach ($validation['warnings'] as $warning) {
                $this->line("        <comment>warning:</comment> {$warning}");
                $warningCount++;
            }
        }

        if ($errorCount > 0) {
            $this->error("Import aborted: {$errorCount} validation error(s), no database rows changed.");

            return self::FAILURE;
        }

        $rows = $this->changedRows($artifacts, $locale);

        if ($this->option('dry-run')) {
            $verseCount = collect($artifacts)->sum(
                fn (array $artifact): int => count($artifact['analysis']['verses'])
            );
            $this->info(
                'Dry run complete: '.count($artifacts)." chapter(s), {$verseCount} verse(s), "
                .count($rows['records'])." database change(s), {$warningCount} warning(s)."
            );

            return self::SUCCESS;
        }

        try {
            $deleted = DB::transaction(function () use ($rows, $artifacts, $locale, $path): int {
                foreach (array_chunk($rows['records'], 500) as $chunk) {
                    GreekVerseAnalysis::query()->upsert(
                        $chunk,
                        ['greek_source', 'locale', 'gepi'],
                        [
                            'format_version',
                            'analysis',
                            'generated_by',
                            'generated_at',
                            'source_key',
                            'content_hash',
                            'updated_at',
                        ]
                    );
                }

                return $this->option('prune')
                    ? $this->pruneMissingRows($artifacts, $locale, $path)
                    : 0;
            });
        } catch (Throwable $exception) {
            $this->error("Database import failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(
            'Imported '.count($artifacts)." chapter(s): {$rows['inserted']} inserted, "
            ."{$rows['updated']} updated, {$rows['unchanged']} unchanged, {$deleted} pruned."
        );

        return self::SUCCESS;
    }

    private function read(FilesystemAdapter $storage, string $sourceKey): string
    {
        $stream = $storage->readStream($sourceKey);

        if (! is_resource($stream)) {
            throw new RuntimeException("Could not open {$sourceKey}.");
        }

        try {
            $contents = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if ($contents === false) {
            throw new RuntimeException("Could not read {$sourceKey}.");
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid JSON: {$exception->getMessage()}", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The JSON root must be an object.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return Collection<int, GreekVerse>
     */
    private function chapterVerses(array $analysis): Collection
    {
        $usxCode = $analysis['usxCode'] ?? null;
        $chapter = $analysis['chapter'] ?? null;
        $greekSource = $analysis['greekSource'] ?? null;

        if (! is_string($usxCode) || ! is_int($chapter) || ! is_string($greekSource)) {
            return collect();
        }

        return GreekVerse::query()
            ->where('source', $greekSource)
            ->where('usx_code', $usxCode)
            ->where('chapter', $chapter)
            ->orderBy('verse')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, string>
     */
    private function filenameErrors(string $sourceKey, array $analysis): array
    {
        $expected = ($analysis['usxCode'] ?? '').'_'.($analysis['chapter'] ?? '').'.json';

        return basename($sourceKey) === $expected
            ? []
            : ["The filename must be \"{$expected}\" for its chapter header."];
    }

    /**
     * @param  array<int, array{source_key: string, analysis: array<string, mixed>}>  $artifacts
     * @return array{records: array<int, array<string, mixed>>, inserted: int, updated: int, unchanged: int}
     */
    private function changedRows(array $artifacts, string $locale): array
    {
        $records = [];
        $inserted = 0;
        $updated = 0;
        $unchanged = 0;
        $now = now();

        foreach ($artifacts as $artifact) {
            $analysis = $artifact['analysis'];
            $greekSource = (string) $analysis['greekSource'];
            $gepis = array_column($analysis['verses'], 'gepi');
            $existingAnalyses = GreekVerseAnalysis::query()
                ->where('greek_source', $greekSource)
                ->where('locale', $locale)
                ->whereIn('gepi', $gepis)
                ->get([
                    'gepi',
                    'format_version',
                    'generated_by',
                    'generated_at',
                    'source_key',
                    'content_hash',
                ])
                ->keyBy('gepi');

            foreach ($analysis['verses'] as $verseAnalysis) {
                $encoded = json_encode(
                    $verseAnalysis,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                $contentHash = hash('sha256', $encoded);
                $gepi = (string) $verseAnalysis['gepi'];
                $existingAnalysis = $existingAnalyses->get($gepi);

                if (
                    $existingAnalysis !== null
                    && $existingAnalysis->content_hash === $contentHash
                    && $existingAnalysis->format_version === (int) $analysis['format']
                    && $existingAnalysis->generated_by === (string) $analysis['createdBy']
                    && $existingAnalysis->generated_at->toDateString() === (string) $analysis['createdAt']
                    && $existingAnalysis->source_key === $artifact['source_key']
                ) {
                    $unchanged++;

                    continue;
                }

                $existingAnalysis === null ? $inserted++ : $updated++;
                $records[] = [
                    'gepi' => $gepi,
                    'greek_source' => $greekSource,
                    'locale' => $locale,
                    'format_version' => (int) $analysis['format'],
                    'analysis' => $encoded,
                    'generated_by' => (string) $analysis['createdBy'],
                    'generated_at' => (string) $analysis['createdAt'],
                    'source_key' => $artifact['source_key'],
                    'content_hash' => $contentHash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return compact('records', 'inserted', 'updated', 'unchanged');
    }

    /**
     * @param  array<int, array{source_key: string, analysis: array<string, mixed>}>  $artifacts
     */
    private function pruneMissingRows(array $artifacts, string $locale, string $path): int
    {
        $importedKeys = [];

        foreach ($artifacts as $artifact) {
            foreach ($artifact['analysis']['verses'] as $verseAnalysis) {
                $importedKeys[
                    $artifact['analysis']['greekSource'].'|'.$locale.'|'.$verseAnalysis['gepi']
                ] = true;
            }
        }

        $prefix = rtrim($path, '/').'/';
        $idsToDelete = GreekVerseAnalysis::query()
            ->where('locale', $locale)
            ->get(['id', 'greek_source', 'gepi', 'source_key'])
            ->filter(function (GreekVerseAnalysis $analysis) use ($prefix, $importedKeys, $locale): bool {
                $naturalKey = "{$analysis->greek_source}|{$locale}|{$analysis->gepi}";

                return Str::startsWith($analysis->source_key, $prefix)
                    && ! isset($importedKeys[$naturalKey]);
            })
            ->pluck('id');

        if ($idsToDelete->isEmpty()) {
            return 0;
        }

        return GreekVerseAnalysis::query()->whereIn('id', $idsToDelete)->delete();
    }
}
