<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use SzentirasHu\Service\VerseAnalysis\VerseAnalysisContextExporter;
use Throwable;

class ExportVerseAnalysisContext extends Command
{
    protected $signature = 'szentiras:export-verse-analysis-context
        {reference : Exactly one New Testament chapter, for example "Jn 3".}
        {--dir=storage/app/verse-analysis : Root directory for chapter work files, relative to the project root.}
        {--chunk-words=180 : Maximum Greek words per chunk; a single verse is never split.}
        {--translations=SZIT,KNB,STL,RUF : Comma-separated Hungarian translations.}
        {--json : Print the generated manifest as JSON.}';

    protected $description = 'Export compact, chunked source data for semantic Greek verse analysis.';

    public function handle(VerseAnalysisContextExporter $exporter): int
    {
        $directory = $this->absolutePath((string) $this->option('dir'));
        $translations = array_values(array_filter(array_map(
            static fn (string $translation): string => trim($translation),
            explode(',', (string) $this->option('translations')),
        )));

        try {
            $manifest = $exporter->export(
                (string) $this->argument('reference'),
                $directory,
                (int) $this->option('chunk-words'),
                $translations,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                $manifest,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->info(sprintf(
                'Exported %s into %d chunk(s): %s',
                $manifest['chapterKey'],
                count($manifest['chunks']),
                $manifest['manifestPath'],
            ));
        }

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);
    }
}
