<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Http\Requests\ReorderGuidesRequest;
use SzentirasHu\Http\Requests\StoreGuideRequest;
use SzentirasHu\Http\Requests\UpdateGuideRequest;
use SzentirasHu\Models\Guide;
use SzentirasHu\Models\Tag;

class GuideController extends Controller
{
    public function index(): View
    {
        return view('editor.guides.index', [
            'guides' => Guide::query()
                ->with('tags')
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('editor.guides.create');
    }

    public function store(StoreGuideRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $guide = DB::transaction(function () use ($request, $validated): Guide {
            $guide = Guide::query()->create([
                'title' => $validated['title'],
                'slug' => $this->uniqueSlug($validated['title']),
                'content' => $validated['content'],
                'is_active' => $request->boolean('is_active'),
                'position' => ((int) Guide::query()->max('position')) + 1,
            ]);

            $this->syncTags($guide, $validated['tags'] ?? '');

            return $guide;
        });

        return redirect()->route('editor.guides.edit', $guide)
            ->with('success', 'A bejegyzés létrejött.');
    }

    public function show(Guide $guide): RedirectResponse
    {
        return redirect()->route('editor.guides.edit', $guide);
    }

    public function edit(Guide $guide): View
    {
        $guide->load('tags');

        return view('editor.guides.edit', [
            'guide' => $guide,
        ]);
    }

    public function update(UpdateGuideRequest $request, Guide $guide): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($guide, $request, $validated): void {
            $guide->update([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncTags($guide, $validated['tags'] ?? '');
        });

        return redirect()->route('editor.guides.edit', $guide)
            ->with('success', 'A bejegyzés módosításai elmentve.');
    }

    public function destroy(Guide $guide): RedirectResponse
    {
        $guide->delete();

        $this->normalizePositions();

        return redirect()->route('editor.guides.index')
            ->with('success', 'A bejegyzés törölve.');
    }

    public function toggle(Guide $guide): RedirectResponse
    {
        $guide->update([
            'is_active' => ! $guide->is_active,
        ]);

        return redirect()->route('editor.guides.index')
            ->with('success', $guide->is_active ? 'A bejegyzés aktív.' : 'A bejegyzés inaktív.');
    }

    public function reorder(ReorderGuidesRequest $request): JsonResponse
    {
        $guideIds = $request->validated('guides');
        $allGuideIds = Guide::query()->pluck('id')->all();

        if (count($guideIds) !== count($allGuideIds)) {
            return response()->json([
                'message' => 'A teljes bejegyzéslistát el kell küldeni.',
            ], 422);
        }

        DB::transaction(function () use ($guideIds): void {
            foreach ($guideIds as $index => $guideId) {
                Guide::query()->whereKey($guideId)->update([
                    'position' => $index + 1,
                ]);
            }
        });

        return response()->json([
            'message' => 'A sorrend elmentve.',
        ]);
    }

    private function syncTags(Guide $guide, string $tagNames): void
    {
        $tagIds = collect(explode(',', $tagNames))
            ->map(fn (string $tagName): string => trim($tagName))
            ->filter()
            ->unique(fn (string $tagName): string => Str::lower($tagName))
            ->map(function (string $tagName): int {
                return Tag::query()->firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName],
                )->id;
            })
            ->all();

        $guide->tags()->sync($tagIds);
    }

    private function uniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'cikk';
        $slug = $baseSlug;
        $suffix = 2;

        while (Guide::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function normalizePositions(): void
    {
        $guideIds = Guide::query()->orderBy('position')->orderBy('id')->pluck('id');

        DB::transaction(function () use ($guideIds): void {
            foreach ($guideIds as $index => $guideId) {
                Guide::query()->whereKey($guideId)->update([
                    'position' => $index + 1,
                ]);
            }
        });
    }
}
