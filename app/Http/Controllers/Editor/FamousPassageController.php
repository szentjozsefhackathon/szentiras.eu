<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Http\Requests\StoreFamousPassageRequest;
use SzentirasHu\Http\Requests\UpdateFamousPassageRequest;
use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Service\Cdn\CacheTagCollector;
use SzentirasHu\Service\Cdn\CloudflareCacheService;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Service\FamousPassage\FamousPassageService;

class FamousPassageController extends Controller
{
    public function __construct(
        protected FamousPassageService $famousPassageService,
        protected TranslationRepository $translationRepository,
        protected CloudflareCacheService $cloudflareCacheService,
    ) {}

    public function index(): View
    {
        return view('editor.famousPassages.index', [
            'passages' => FamousPassage::query()->orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('editor.famousPassages.create');
    }

    public function store(StoreFamousPassageRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $famousPassage = FamousPassage::query()->create([
            ...$this->attributes($request),
            'slug' => $validated['slug'] ?? $this->uniqueSlug($validated['name']),
            'position' => $validated['position'] ?? ((int) FamousPassage::query()->max('position')) + 1,
        ]);
        $this->purgeChapterPages([$famousPassage->usx_code]);

        return redirect()->route('editor.famousPassages.edit', $famousPassage)
            ->with('success', 'A szakasz létrejött.');
    }

    public function edit(FamousPassage $famousPassage): View
    {
        return view('editor.famousPassages.edit', [
            'passage' => $famousPassage,
        ]);
    }

    public function update(UpdateFamousPassageRequest $request, FamousPassage $famousPassage): RedirectResponse
    {
        $validated = $request->validated();
        $previousUsxCode = $famousPassage->usx_code;
        $famousPassage->update([
            ...$this->attributes($request),
            'slug' => $validated['slug'] ?? $famousPassage->slug,
            'position' => $validated['position'] ?? $famousPassage->position,
        ]);
        $this->purgeChapterPages([$previousUsxCode, $famousPassage->usx_code]);

        return redirect()->route('editor.famousPassages.edit', $famousPassage)
            ->with('success', 'A szakasz módosításai elmentve.');
    }

    public function destroy(FamousPassage $famousPassage): RedirectResponse
    {
        $famousPassage->delete();
        $this->purgeChapterPages([$famousPassage->usx_code]);

        return redirect()->route('editor.famousPassages.index')
            ->with('success', 'A szakasz törölve.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreFamousPassageRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'alternative_names' => $request->alternativeNames(),
            'reference' => trim($validated['reference']),
            'key_verse' => isset($validated['key_verse']) ? trim($validated['key_verse']) : null,
            ...($this->famousPassageService->locate($validated['reference']) ?? []),
            'introduction' => $validated['introduction'] ?? null,
            'content' => $validated['content'] ?? null,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Chapter pages of the passage's book link to it, and they are cached on the CDN in every translation.
     *
     * @param list<string> $usxCodes
     */
    private function purgeChapterPages(array $usxCodes): void
    {
        $tags = [];
        foreach ($this->translationRepository->getAll() as $translation) {
            foreach (array_unique($usxCodes) as $usxCode) {
                $tags[] = CacheTagCollector::textTag($translation->abbrev, $usxCode);
            }
        }
        $this->cloudflareCacheService->purgeTags($tags);
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'szakasz';
        $slug = $baseSlug;
        $suffix = 2;
        while (FamousPassage::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
