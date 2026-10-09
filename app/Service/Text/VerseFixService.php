<?php

namespace SzentirasHu\Service\Text;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\Enum\VerseFixStatus;
use SzentirasHu\Models\VerseFix;
use SzentirasHu\Models\VerseFixRevision;
use SzentirasHu\Service\Cdn\CacheTagCollector;
use SzentirasHu\Service\Cdn\CloudflareCacheService;

/**
 * Manual corrections of stored verse texts. A fix targets the verse records of one
 * translation + gepi + tip whose text equals the fix's original text, so it survives
 * re-imports and can tell when the publisher has already corrected the source.
 */
class VerseFixService
{
    public function __construct(
        protected CloudflareCacheService $cloudflareCacheService,
        protected TextService $textService,
    ) {}

    /**
     * Create a fix for the given verse record and apply it right away.
     *
     * @throws VerseFixNotApplicableException
     */
    public function create(Verse $verse, string $fixedText, ?string $note, ?string $editorToken): VerseFix
    {
        return DB::transaction(function () use ($verse, $fixedText, $note, $editorToken): VerseFix {
            $verseFix = VerseFix::query()->create([
                'translation_id' => $verse->trans,
                'usx_code' => $verse->usx_code,
                'gepi' => $verse->gepi,
                'chapter' => $verse->chapter,
                'numv' => $verse->numv,
                'tip' => $verse->tip,
                'original_text' => (string) $verse->verse,
                'fixed_text' => $fixedText,
                'note' => $note,
                'status' => VerseFixStatus::Active,
                'last_applied_at' => now(),
            ]);

            if ($this->replaceVerseTexts($verseFix, [$verseFix->original_text], $fixedText) === 0) {
                throw new VerseFixNotApplicableException('A vers szövege időközben megváltozott, töltse be újra.');
            }

            $this->recordRevision($verseFix, VerseFixRevision::SOURCE_EDITOR, $editorToken);

            return $verseFix;
        });
    }

    /**
     * Save a new version of the fix. When $newOriginalText is given, the fix is rebased
     * onto that text (e.g. after an import produced a conflict).
     *
     * @throws VerseFixNotApplicableException
     */
    public function update(VerseFix $verseFix, string $fixedText, ?string $note, ?string $newOriginalText, ?string $editorToken): VerseFix
    {
        return DB::transaction(function () use ($verseFix, $fixedText, $note, $newOriginalText, $editorToken): VerseFix {
            $replaceableTexts = array_filter([$verseFix->original_text, $verseFix->fixed_text, $newOriginalText], fn (?string $text): bool => $text !== null);

            $verseFix->fill([
                'original_text' => $newOriginalText ?? $verseFix->original_text,
                'fixed_text' => $fixedText,
                'note' => $note,
            ]);

            if ($this->replaceVerseTexts($verseFix, array_values(array_unique($replaceableTexts)), $fixedText) === 0) {
                throw new VerseFixNotApplicableException('Sem az eredeti, sem a javított szöveg nem található a versben. Válasszon új alapszöveget.');
            }

            $verseFix->status = VerseFixStatus::Active;
            $verseFix->last_applied_at = now();
            $verseFix->save();

            $this->recordRevision($verseFix, VerseFixRevision::SOURCE_EDITOR, $editorToken);

            return $verseFix;
        });
    }

    /**
     * Withdraw the fix and restore the original text.
     */
    public function disable(VerseFix $verseFix, ?string $editorToken): VerseFix
    {
        return DB::transaction(function () use ($verseFix, $editorToken): VerseFix {
            if ($verseFix->status === VerseFixStatus::Active) {
                $this->replaceVerseTexts($verseFix, [$verseFix->fixed_text], $verseFix->original_text);
            }

            $verseFix->status = VerseFixStatus::Disabled;
            $verseFix->save();

            $this->recordRevision($verseFix, VerseFixRevision::SOURCE_EDITOR, $editorToken);

            return $verseFix;
        });
    }

    /**
     * Re-apply the fixes of a translation after its texts were imported again.
     *
     * @return array<string, int> number of fixes per resulting status value
     */
    public function reapplyForTranslation(int $translationId): array
    {
        $statusCounts = array_fill_keys(array_map(fn (VerseFixStatus $status): string => $status->value, VerseFixStatus::cases()), 0);

        $verseFixes = VerseFix::query()
            ->where('translation_id', $translationId)
            ->whereIn('status', [VerseFixStatus::Active, VerseFixStatus::Conflict])
            ->orderBy('id')
            ->get();

        foreach ($verseFixes as $verseFix) {
            $newStatus = $this->reapply($verseFix);
            $statusCounts[$newStatus->value]++;
        }

        return $statusCounts;
    }

    /**
     * Make the change visible: drop the cached texts of the fix's translation, reindex search and purge the CDN pages showing the fixed book.
     */
    public function refreshCaches(VerseFix $verseFix): void
    {
        $this->textService->invalidateTranslationTexts($verseFix->translation);

        $indexerTrigger = Config::get('settings.sphinxIndexerTrigger');
        if (! $indexerTrigger || ! @touch($indexerTrigger)) {
            Log::warning("Verse fix: could not trigger the Sphinx indexer ({$indexerTrigger}).");
        }

        $this->cloudflareCacheService->purgeTags([
            CacheTagCollector::textTag($verseFix->translation->abbrev, $verseFix->usx_code),
            CacheTagCollector::translationTag($verseFix->translation->abbrev),
        ]);
    }

    /**
     * The current verse records the fix targets (same translation, gepi and tip).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Verse>
     */
    public function findTargetVerses(VerseFix $verseFix): \Illuminate\Database\Eloquent\Collection
    {
        return Verse::query()
            ->where('trans', $verseFix->translation_id)
            ->where('gepi', $verseFix->gepi)
            ->where('tip', $verseFix->tip)
            ->orderBy('id')
            ->get();
    }

    /**
     * The fix that already handles the given verse record, if any.
     */
    public function findFixForVerse(Verse $verse): ?VerseFix
    {
        return VerseFix::query()
            ->where('translation_id', $verse->trans)
            ->where('gepi', $verse->gepi)
            ->where('tip', $verse->tip)
            ->where(function ($query) use ($verse): void {
                $query->where('original_text', $verse->verse)
                    ->orWhere('fixed_text', $verse->verse);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function reapply(VerseFix $verseFix): VerseFixStatus
    {
        $previousStatus = $verseFix->status;

        if ($this->replaceVerseTexts($verseFix, [$verseFix->original_text], $verseFix->fixed_text) > 0) {
            $verseFix->status = VerseFixStatus::Active;
            $verseFix->last_applied_at = now();
        } elseif ($this->findTargetVerses($verseFix)->contains(fn (Verse $verse): bool => $verse->verse === $verseFix->fixed_text)) {
            $verseFix->status = VerseFixStatus::ResolvedUpstream;
        } else {
            $verseFix->status = VerseFixStatus::Conflict;
        }

        $verseFix->save();

        if ($verseFix->status !== $previousStatus) {
            $this->recordRevision($verseFix, VerseFixRevision::SOURCE_IMPORT, null);
        }

        return $verseFix->status;
    }

    /**
     * @param  list<string>  $searchedTexts
     * @return int number of updated verse records
     */
    private function replaceVerseTexts(VerseFix $verseFix, array $searchedTexts, string $newText): int
    {
        return Verse::query()
            ->where('trans', $verseFix->translation_id)
            ->where('gepi', $verseFix->gepi)
            ->where('tip', $verseFix->tip)
            ->whereIn('verse', $searchedTexts)
            ->update(['verse' => $newText]);
    }

    private function recordRevision(VerseFix $verseFix, string $source, ?string $editorToken): void
    {
        $verseFix->revisions()->create([
            'original_text' => $verseFix->original_text,
            'fixed_text' => $verseFix->fixed_text,
            'note' => $verseFix->note,
            'status' => $verseFix->status,
            'source' => $source,
            'editor_token' => $editorToken,
        ]);
    }
}
