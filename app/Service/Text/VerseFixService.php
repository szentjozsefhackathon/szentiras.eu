<?php

namespace SzentirasHu\Service\Text;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Data\Entity\Translation;
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
 * A fix may also change the verse type of the records, e.g. to hide a stray line.
 */
class VerseFixService
{
    /**
     * A type no translation displays: the source's own code for a struck-out line.
     */
    public const HIDDEN_VERSE_TYPE = 850;

    private const VERSE_TYPE_LABELS = [
        'text' => 'Szöveg',
        'poemLine' => 'Verssor',
        'footnote' => 'Lábjegyzet',
        'footnoteInterval' => 'Lábjegyzet versei',
        'xref' => 'Kereszthivatkozás',
    ];

    public function __construct(
        protected CloudflareCacheService $cloudflareCacheService,
        protected TextService $textService,
    ) {}

    /**
     * Create a fix for the given verse record and apply it right away.
     *
     * @throws VerseFixNotApplicableException
     */
    public function create(Verse $verse, string $fixedText, ?int $fixedTip, ?string $note, ?string $editorToken): VerseFix
    {
        return DB::transaction(function () use ($verse, $fixedText, $fixedTip, $note, $editorToken): VerseFix {
            $verseFix = VerseFix::query()->create([
                'translation_id' => $verse->trans,
                'usx_code' => $verse->usx_code,
                'gepi' => $verse->gepi,
                'chapter' => $verse->chapter,
                'numv' => $verse->numv,
                'tip' => $verse->tip,
                'original_text' => (string) $verse->verse,
                'fixed_text' => $fixedText,
                'fixed_tip' => $fixedTip === $verse->tip ? null : $fixedTip,
                'note' => $note,
                'status' => VerseFixStatus::Active,
                'last_applied_at' => now(),
            ]);

            if ($this->replaceVerseRecords($verseFix, [$verseFix->original_text], [$verseFix->tip], $fixedText, $verseFix->targetTip()) === 0) {
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
    public function update(VerseFix $verseFix, string $fixedText, ?int $fixedTip, ?string $note, ?string $newOriginalText, ?string $editorToken): VerseFix
    {
        return DB::transaction(function () use ($verseFix, $fixedText, $fixedTip, $note, $newOriginalText, $editorToken): VerseFix {
            $replaceableTexts = array_filter([$verseFix->original_text, $verseFix->fixed_text, $newOriginalText], fn (?string $text): bool => $text !== null);
            $replaceableTips = array_values(array_unique([$verseFix->tip, $verseFix->targetTip()]));

            $verseFix->fill([
                'original_text' => $newOriginalText ?? $verseFix->original_text,
                'fixed_text' => $fixedText,
                'fixed_tip' => $fixedTip === $verseFix->tip ? null : $fixedTip,
                'note' => $note,
            ]);

            if ($this->replaceVerseRecords($verseFix, array_values(array_unique($replaceableTexts)), $replaceableTips, $fixedText, $verseFix->targetTip()) === 0) {
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
                $this->replaceVerseRecords($verseFix, [$verseFix->fixed_text], [$verseFix->targetTip()], $verseFix->original_text, $verseFix->tip);
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
     * The current verse records the fix targets (same translation and gepi, original or changed tip).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Verse>
     */
    public function findTargetVerses(VerseFix $verseFix): \Illuminate\Database\Eloquent\Collection
    {
        return Verse::query()
            ->where('trans', $verseFix->translation_id)
            ->where('gepi', $verseFix->gepi)
            ->whereIn('tip', array_unique([$verseFix->tip, $verseFix->targetTip()]))
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
            ->where(function ($query) use ($verse): void {
                $query->where('tip', $verse->tip)
                    ->orWhere('fixed_tip', $verse->tip);
            })
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

        if ($this->replaceVerseRecords($verseFix, [$verseFix->original_text], [$verseFix->tip], $verseFix->fixed_text, $verseFix->targetTip()) > 0) {
            $verseFix->status = VerseFixStatus::Active;
            $verseFix->last_applied_at = now();
        } elseif ($this->findTargetVerses($verseFix)->contains(fn (Verse $verse): bool => $verse->verse === $verseFix->fixed_text && $verse->tip === $verseFix->targetTip())) {
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
     * The verse types an editor can give a record of the translation, labelled by role,
     * plus the record's current type and the hidden type.
     *
     * @return array<int, string> label keyed by verse type
     */
    public function verseTypeOptions(Translation $translation, int ...$currentTips): array
    {
        $options = [];
        foreach (Config::get("translations.definitions.{$translation->abbrev}.verseTypes", []) as $role => $tips) {
            foreach ($tips as $key => $value) {
                if ($role === 'heading') {
                    $options[$key] = 'Címsor ('.($value + 1).'. szint)';
                } else {
                    $options[$value] = self::VERSE_TYPE_LABELS[$role] ?? $role;
                }
            }
        }
        $options[self::HIDDEN_VERSE_TYPE] ??= 'Kihúzott sor, nem jelenik meg';
        foreach ($currentTips as $tip) {
            $options[$tip] ??= 'Nincs beállítva, nem jelenik meg';
        }
        ksort($options);

        return $options;
    }

    /**
     * @param  list<string>  $searchedTexts
     * @param  list<int>  $searchedTips
     * @return int number of updated verse records
     */
    private function replaceVerseRecords(VerseFix $verseFix, array $searchedTexts, array $searchedTips, string $newText, int $newTip): int
    {
        return Verse::query()
            ->where('trans', $verseFix->translation_id)
            ->where('gepi', $verseFix->gepi)
            ->whereIn('tip', $searchedTips)
            ->whereIn('verse', $searchedTexts)
            ->update(['verse' => $newText, 'tip' => $newTip]);
    }

    private function recordRevision(VerseFix $verseFix, string $source, ?string $editorToken): void
    {
        $verseFix->revisions()->create([
            'original_text' => $verseFix->original_text,
            'fixed_text' => $verseFix->fixed_text,
            'fixed_tip' => $verseFix->fixed_tip,
            'note' => $verseFix->note,
            'status' => $verseFix->status,
            'source' => $source,
            'editor_token' => $editorToken,
        ]);
    }
}
