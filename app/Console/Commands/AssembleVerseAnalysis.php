<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use SzentirasHu\Service\VerseAnalysis\VerseAnalysisAssembler;
use Throwable;

class AssembleVerseAnalysis extends Command
{
    protected $signature = 'szentiras:assemble-verse-analysis
        {manifest : Path to the compact exporter manifest, relative to the local disk or absolute.}
        {--output-dir=greek/verse-analysis/OpenGNT/hu/v1 : Analysis output directory, relative to the local disk or absolute.}
        {--created-by=claude-semantic-pipeline : Value for the final JSON createdBy field.}';

    protected $description = 'Assemble semantic chunk results into a complete verse-analysis JSON file.';

    public function handle(VerseAnalysisAssembler $assembler): int
    {
        try {
            $outputPath = $assembler->assemble(
                $this->absolutePath((string) $this->argument('manifest')),
                $this->absolutePath((string) $this->option('output-dir')),
                (string) $this->option('created-by'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Assembled verse analysis: {$outputPath}");

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : Storage::disk('local')->path(trim($path, DIRECTORY_SEPARATOR));
    }
}
