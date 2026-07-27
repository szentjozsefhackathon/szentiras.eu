<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SzentirasHu\Mcp\GreekReferenceResolver;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\VerseAnalysis\VerseAnalysisValidator;

/**
 * Validates the hand-curated, per chapter Greek verse analysis files against the Greek text
 * stored in the database.
 *
 * The files are produced chapter by chapter by agents (see the `gorog-elemzes` skill), so this
 * command is both their feedback loop and the guarantee that the files can later be imported:
 * every verse of the chapter has to be present, the Greek text has to match the database
 * letter by letter, and the segments have to partition the word indexes of the verse exactly.
 */
class ValidateVerseAnalysis extends Command
{
    protected $signature = 'szentiras:validate-verse-analysis
        {reference? : New Testament reference in Hungarian notation, e.g. "Jn" or "Jn 3". Defaults to the whole New Testament.}
        {--dir=greek/verse-analysis/OpenGNT/hu/v1 : Directory holding the per chapter analysis files, relative to the local disk or absolute.}
        {--missing : List only the chapters that have no analysis file yet.}
        {--json : Output the result as JSON instead of one line per chapter.}';

    protected $description = 'Validate the per chapter Greek verse analysis JSON files against the stored Greek text.';

    public function __construct(
        private readonly GreekReferenceResolver $referenceResolver,
        private readonly VerseAnalysisValidator $validator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $verses = $this->versesToCheck();

        if ($verses === null) {
            return self::FAILURE;
        }

        if ($verses->isEmpty()) {
            $this->error('No Greek verses found for the reference. Only the New Testament exists in Greek.');

            return self::FAILURE;
        }

        $results = $this->chaptersOf($verses)
            ->map(fn (Collection $chapterVerses): array => $this->validateChapter($chapterVerses))
            ->values();

        if ($this->option('missing')) {
            return $this->reportMissing($results);
        }

        return $this->report($results);
    }

    /**
     * The Greek verses the reference covers, or every Greek verse when no reference is given.
     *
     * @return Collection<int, GreekVerse>|null Null when the reference could not be parsed.
     */
    private function versesToCheck(): ?Collection
    {
        $reference = $this->argument('reference');

        if ($reference === null) {
            return GreekVerse::query()
                ->orderBy('usx_code')
                ->orderBy('chapter')
                ->orderBy('verse')
                ->get();
        }

        try {
            $canonicalReference = CanonicalReference::fromString($reference);
        } catch (ParsingException) {
            $this->error("Could not parse the reference '{$reference}'. Use Hungarian notation, for example 'Jn', 'Jn 3' or 'Jn 1;3'.");

            return null;
        }

        return $this->referenceResolver->versesFor($canonicalReference);
    }

    /**
     * The verses grouped into chapters, keyed by `USX_chapter`, in canonical order.
     *
     * @param  Collection<int, GreekVerse>  $verses
     * @return Collection<string, Collection<int, GreekVerse>>
     */
    private function chaptersOf(Collection $verses): Collection
    {
        return $verses
            ->sortBy([['usx_code', 'asc'], ['chapter', 'asc'], ['verse', 'asc']])
            ->groupBy(fn (GreekVerse $verse): string => "{$verse->usx_code}_{$verse->chapter}");
    }

    /**
     * @param  Collection<int, GreekVerse>  $chapterVerses
     * @return array{chapter: string, path: string, exists: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    private function validateChapter(Collection $chapterVerses): array
    {
        $first = $chapterVerses->first();
        $chapterKey = "{$first->usx_code}_{$first->chapter}";
        $path = $this->pathFor($chapterKey);

        if (! is_file($path)) {
            return [
                'chapter' => $chapterKey,
                'path' => $path,
                'exists' => false,
                'errors' => ['The analysis file does not exist.'],
                'warnings' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [
                'chapter' => $chapterKey,
                'path' => $path,
                'exists' => true,
                'errors' => ['The file is not valid JSON: '.json_last_error_msg().'.'],
                'warnings' => [],
            ];
        }

        $validation = $this->validator->validate($decoded, $chapterVerses);

        return [
            'chapter' => $chapterKey,
            'path' => $path,
            'exists' => true,
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
        ];
    }

    private function pathFor(string $chapterKey): string
    {
        $directory = (string) $this->option('dir');

        if (! Str::startsWith($directory, '/')) {
            $directory = Storage::disk('local')->path(trim($directory, '/'));
        }

        return rtrim($directory, '/')."/{$chapterKey}.json";
    }

    /**
     * The chapters that have no analysis file yet.
     *
     * @param  Collection<int, array{chapter: string, path: string, exists: bool, errors: array<int, string>, warnings: array<int, string>}>  $results
     */
    private function reportMissing(Collection $results): int
    {
        $missing = $results->reject(fn (array $result): bool => $result['exists'])->values();

        if ($this->option('json')) {
            $this->line(json_encode($missing->pluck('chapter')->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($missing as $result) {
            $this->line($result['chapter']);
        }

        $this->info("{$missing->count()} chapter(s) missing out of {$results->count()}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array{chapter: string, path: string, exists: bool, errors: array<int, string>, warnings: array<int, string>}>  $results
     */
    private function report(Collection $results): int
    {
        $failed = $results->filter(fn (array $result): bool => $result['errors'] !== []);

        if ($this->option('json')) {
            $this->line(json_encode($results->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        foreach ($results as $result) {
            if ($result['errors'] === []) {
                $this->line("<info>OK</info>    {$result['chapter']}".($result['warnings'] === [] ? '' : ' ('.count($result['warnings']).' warning(s))'));
            } else {
                $this->line("<error>FAIL</error>  {$result['chapter']}");
            }

            foreach ($result['errors'] as $error) {
                $this->line("        {$error}");
            }

            foreach ($result['warnings'] as $warning) {
                $this->line("        <comment>warning:</comment> {$warning}");
            }
        }

        if ($failed->isEmpty()) {
            $this->info("{$results->count()} chapter(s) validated, all good.");

            return self::SUCCESS;
        }

        $this->error("{$failed->count()} of {$results->count()} chapter(s) failed validation.");

        return self::FAILURE;
    }
}
