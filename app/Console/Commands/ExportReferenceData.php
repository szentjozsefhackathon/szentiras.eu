<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use SzentirasHu\Service\Reference\ReferenceDataService;

/**
 * Writes the reference parser's data to a file, byte for byte what /api/reference-data serves.
 * Other projects fetch it from the API; this is for working offline against a local checkout.
 */
class ExportReferenceData extends Command
{
    protected $signature = 'szentiras:export-reference-data
                            {--path= : Absolute path of the JSON file (defaults to storage/app/reference-data.json)}';

    protected $description = 'Export book abbreviations and numbering schemes used by the reference parser as JSON.';

    public function handle(ReferenceDataService $referenceDataService): int
    {
        $path = $this->option('path') ?: storage_path('app/reference-data.json');

        if (file_put_contents($path, $referenceDataService->json()) === false) {
            $this->error("Failed to write reference data to {$path}.");

            return self::FAILURE;
        }

        $this->info("Reference data version {$referenceDataService->version()} written to {$path}.");

        return self::SUCCESS;
    }
}
