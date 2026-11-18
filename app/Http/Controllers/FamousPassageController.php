<?php

namespace SzentirasHu\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Service\FamousPassage\FamousPassageService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

/**
 * Pages of well-known passages ("23. zsoltár", "Hegyi beszéd"): one page per passage that
 * collects its text, its key verse in every translation and its explanation, so a search for
 * the passage's name has a single strong page to land on.
 */
class FamousPassageController extends Controller
{
    public function __construct(
        protected FamousPassageService $famousPassageService,
        protected TranslationService $translationService,
        protected TextService $textService,
    ) {}

    public function index(): View
    {
        return view('famousPassages.index', [
            'passages' => FamousPassage::query()->active()->get(),
        ]);
    }

    /**
     * Inactive passages are visible to editors only, so drafts can be reviewed in place.
     */
    public function show(FamousPassage $famousPassage, EditorService $editorService): View
    {
        abort_unless($famousPassage->is_active || $editorService->currentIsEditor(), 404);

        $translation = $this->translationService->getDefaultTranslation();
        $verseContainers = $this->famousPassageService->verses($famousPassage, $translation);

        return view('famousPassages.show', [
            'passage' => $famousPassage,
            'translation' => $translation,
            'verseContainers' => $verseContainers,
            'translationRows' => $this->famousPassageService->translationRows($famousPassage),
            'magisterUrl' => config('services.magister.brand_public') ? rtrim((string) config('services.magister.public_url'), '/') : null,
            'pageTitle' => $famousPassage->seo_title ?: "{$famousPassage->displayName()} – szöveg, magyarázat, fordítások",
            'teaser' => $famousPassage->seo_description
                ?: Str::limit($famousPassage->introduction ?: $this->textService->getTeaser($verseContainers), 300, '…', true),
        ]);
    }
}
