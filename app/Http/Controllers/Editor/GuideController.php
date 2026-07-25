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

class GuideController extends Controller
{
    public function index(): View
    {
        return view('editor.guides.index', [
            'guides' => Guide::query()->orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('editor.guides.create');
    }

    public function store(StoreGuideRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $guide = Guide::query()->create([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'content' => $validated['content'],
            'is_active' => $request->boolean('is_active'),
            'position' => ((int) Guide::query()->max('position')) + 1,
        ]);

        return redirect()->route('editor.guides.edit', $guide)
            ->with('success', 'Az útmutató létrejött.');
    }

    public function show(Guide $guide): RedirectResponse
    {
        return redirect()->route('editor.guides.edit', $guide);
    }

    public function edit(Guide $guide): View
    {
        return view('editor.guides.edit', [
            'guide' => $guide,
        ]);
    }

    public function update(UpdateGuideRequest $request, Guide $guide): RedirectResponse
    {
        $validated = $request->validated();

        $guide->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('editor.guides.edit', $guide)
            ->with('success', 'Az útmutató módosításai elmentve.');
    }

    public function destroy(Guide $guide): RedirectResponse
    {
        $guide->delete();

        $this->normalizePositions();

        return redirect()->route('editor.guides.index')
            ->with('success', 'Az útmutató törölve.');
    }

    public function toggle(Guide $guide): RedirectResponse
    {
        $guide->update([
            'is_active' => ! $guide->is_active,
        ]);

        return redirect()->route('editor.guides.index')
            ->with('success', $guide->is_active ? 'Az útmutató aktív.' : 'Az útmutató inaktív.');
    }

    public function reorder(ReorderGuidesRequest $request): JsonResponse
    {
        $guideIds = $request->validated('guides');
        $allGuideIds = Guide::query()->pluck('id')->all();

        if (count($guideIds) !== count($allGuideIds)) {
            return response()->json([
                'message' => 'A teljes útmutatólistát el kell küldeni.',
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

    private function uniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'utmutato';
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
