<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\Enum\VerseFixStatus;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Http\Requests\StoreVerseFixRequest;
use SzentirasHu\Http\Requests\UpdateVerseFixRequest;
use SzentirasHu\Models\VerseFix;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\VerseFixNotApplicableException;
use SzentirasHu\Service\Text\VerseFixService;

class VerseFixEditorController extends Controller
{
    private const PAGE_SIZE = 30;

    private const MAX_LOOKUP_RECORDS = 500;

    public function __construct(
        protected VerseFixService $verseFixService,
        protected TextService $textService,
        protected TranslationRepository $translationRepository,
    ) {}

    /**
     * List the fixes and look up verse records of a reference to fix.
     */
    public function index(Request $request): View
    {
        $translationId = $request->integer('translation_id') ?: null;
        $status = VerseFixStatus::tryFrom((string) $request->query('status', ''));
        $reference = trim((string) $request->query('reference', ''));

        $verseFixes = VerseFix::query()
            ->with('translation')
            ->when($translationId, fn ($query) => $query->where('translation_id', $translationId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate(self::PAGE_SIZE)
            ->appends($request->only(['translation_id', 'status', 'reference']));

        $lookupError = null;
        $lookupVerses = collect();
        $translation = $translationId ? Translation::query()->find($translationId) : null;
        if ($reference !== '') {
            if (! $translation) {
                $lookupError = 'Válasszon fordítást a kereséshez.';
            } else {
                try {
                    $lookupVerses = $this->lookupVerses(CanonicalReference::fromString($reference, $translation->id), $translation);
                    if ($lookupVerses->isEmpty()) {
                        $lookupError = 'Nincs ilyen vers ebben a fordításban.';
                    }
                } catch (ParsingException) {
                    $lookupError = 'Nem értelmezhető hivatkozás.';
                }
            }
        }

        return view('editor.verseFixes.index', [
            'verseFixes' => $verseFixes,
            'bookAbbrevs' => $this->bookAbbrevs($verseFixes->getCollection()),
            'translations' => $this->translationRepository->getAll(),
            'statuses' => VerseFixStatus::cases(),
            'translationId' => $translationId,
            'status' => $status,
            'reference' => $reference,
            'lookupVerses' => $lookupVerses,
            'lookupError' => $lookupError,
        ]);
    }

    /**
     * Show the form for fixing one verse record.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $verse = Verse::query()->with(['book', 'translation'])->findOrFail($request->integer('verse_id'));

        $existingFix = $this->verseFixService->findFixForVerse($verse);
        if ($existingFix) {
            return redirect()->route('editor.verseFixes.edit', $existingFix);
        }

        return view('editor.verseFixes.edit', [
            'verseFix' => null,
            'verse' => $verse,
            'translation' => $verse->translation,
            'bookAbbrev' => $verse->book->abbrev,
            'targetVerses' => collect(),
        ]);
    }

    public function store(StoreVerseFixRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $verse = Verse::query()->findOrFail($validated['verse_id']);

        if ($this->verseFixService->findFixForVerse($verse)) {
            return back()->withInput()->withErrors(['fixed_text' => 'Ehhez a vershez már tartozik javítás.']);
        }
        if ($validated['fixed_text'] === $verse->verse) {
            return back()->withInput()->withErrors(['fixed_text' => 'A javított szöveg megegyezik az eredetivel.']);
        }

        try {
            $verseFix = $this->verseFixService->create($verse, $validated['fixed_text'], $validated['note'] ?? null, $this->editorToken($request));
        } catch (VerseFixNotApplicableException $exception) {
            return back()->withInput()->withErrors(['fixed_text' => $exception->getMessage()]);
        }
        $this->verseFixService->refreshCaches($verseFix);

        return redirect()->route('editor.verseFixes.edit', $verseFix)
            ->with('success', 'A javítás elmentve és alkalmazva.');
    }

    public function edit(VerseFix $verseFix): View
    {
        $verseFix->load(['translation', 'revisions']);

        return view('editor.verseFixes.edit', [
            'verseFix' => $verseFix,
            'verse' => null,
            'translation' => $verseFix->translation,
            'bookAbbrev' => $this->bookAbbrevs(collect([$verseFix]))[$this->bookKey($verseFix->translation_id, $verseFix->usx_code)] ?? $verseFix->usx_code,
            'targetVerses' => $this->verseFixService->findTargetVerses($verseFix),
        ]);
    }

    public function update(UpdateVerseFixRequest $request, VerseFix $verseFix): RedirectResponse
    {
        $validated = $request->validated();

        $newOriginalText = null;
        if (! empty($validated['base_verse_id'])) {
            $baseVerse = $this->verseFixService->findTargetVerses($verseFix)->firstWhere('id', (int) $validated['base_verse_id']);
            if (! $baseVerse) {
                return back()->withInput()->withErrors(['base_verse_id' => 'A választott alapszöveg nem ehhez a vershez tartozik.']);
            }
            $newOriginalText = (string) $baseVerse->verse;
        }

        if ($validated['fixed_text'] === ($newOriginalText ?? $verseFix->original_text)) {
            return back()->withInput()->withErrors(['fixed_text' => 'A javított szöveg megegyezik az eredetivel.']);
        }

        try {
            $this->verseFixService->update($verseFix, $validated['fixed_text'], $validated['note'] ?? null, $newOriginalText, $this->editorToken($request));
        } catch (VerseFixNotApplicableException $exception) {
            return back()->withInput()->withErrors(['fixed_text' => $exception->getMessage()]);
        }
        $this->verseFixService->refreshCaches($verseFix);

        return redirect()->route('editor.verseFixes.edit', $verseFix)
            ->with('success', 'A javítás új változata elmentve és alkalmazva.');
    }

    /**
     * Withdraw the fix and restore the original text.
     */
    public function disable(Request $request, VerseFix $verseFix): RedirectResponse
    {
        $this->verseFixService->disable($verseFix, $this->editorToken($request));
        $this->verseFixService->refreshCaches($verseFix);

        return redirect()->route('editor.verseFixes.edit', $verseFix)
            ->with('success', 'A javítás kikapcsolva, az eredeti szöveg visszaállítva.');
    }

    /**
     * @return Collection<int, array{verse: Verse, verseFix: VerseFix|null}>
     */
    private function lookupVerses(CanonicalReference $reference, Translation $translation): Collection
    {
        $lookupVerses = collect();
        foreach ($this->textService->getTranslatedVerses($reference, $translation) as $verseContainer) {
            $verses = Verse::query()
                ->with('book')
                ->where('trans', $translation->id)
                ->where('book_id', $verseContainer->book->id)
                ->whereIn('gepi', array_keys($verseContainer->rawVerses))
                ->orderBy('id')
                ->limit(self::MAX_LOOKUP_RECORDS)
                ->get();
            foreach ($verses as $verse) {
                $lookupVerses->push([
                    'verse' => $verse,
                    'verseFix' => $this->verseFixService->findFixForVerse($verse),
                ]);
            }
        }

        return $lookupVerses->take(self::MAX_LOOKUP_RECORDS);
    }

    /**
     * @param  Collection<int, VerseFix>  $verseFixes
     * @return array<string, string> book abbreviation keyed by bookKey()
     */
    private function bookAbbrevs(Collection $verseFixes): array
    {
        if ($verseFixes->isEmpty()) {
            return [];
        }

        return Book::query()
            ->whereIn('translation_id', $verseFixes->pluck('translation_id')->unique())
            ->whereIn('usx_code', $verseFixes->pluck('usx_code')->unique())
            ->get()
            ->mapWithKeys(fn (Book $book): array => [$this->bookKey($book->translation_id, $book->usx_code) => $book->abbrev])
            ->all();
    }

    private function bookKey(int $translationId, string $usxCode): string
    {
        return "{$translationId}_{$usxCode}";
    }

    private function editorToken(Request $request): ?string
    {
        return $request->session()->get('anonymous_token');
    }
}
