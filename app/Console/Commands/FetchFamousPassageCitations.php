<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\FamousPassage\ChurchCitationService;
use SzentirasHu\Service\Magister\MagisterUnavailableException;

/**
 * Collects from Magister where the Church's documents cite each famous passage, and stores the
 * quotations with the passage. Run it after adding a passage or changing its reference.
 */
class FetchFamousPassageCitations extends Command
{
    protected $signature = 'szentiras:famous-passage-citations
        {slugs?* : Only these passages (default: all)}';

    protected $description = 'Collect the Church documents citing the famous passages from Hittéka (Magister).';

    public function handle(ChurchCitationService $churchCitationService): int
    {
        $slugs = (array) $this->argument('slugs');
        $passages = FamousPassage::query()
            ->when($slugs !== [], fn ($query) => $query->whereIn('slug', $slugs))
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $unknownSlugs = array_diff($slugs, $passages->pluck('slug')->all());
        foreach ($unknownSlugs as $slug) {
            $this->error("Nincs ilyen szakasz: {$slug}");
        }

        $failed = false;
        foreach ($passages as $passage) {
            try {
                $groups = $churchCitationService->collect($passage);
            } catch (MagisterUnavailableException $exception) {
                $this->error("{$passage->slug}: a Hittéka nem érhető el ({$exception->getMessage()})");
                $failed = true;

                continue;
            }

            $passage->update(['church_citations' => $groups, 'church_citations_fetched_at' => now()]);
            $this->info(sprintf(
                '%s (%s): %s',
                $passage->slug,
                $passage->reference,
                $groups === []
                    ? 'nincs idézet'
                    : implode(', ', array_map(fn (array $group): string => "{$group['key']} ".count($group['citations'])."/{$group['total']}", $groups)),
            ));
        }

        return $failed || $unknownSlugs !== [] ? self::FAILURE : self::SUCCESS;
    }
}
