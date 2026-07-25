<?php

namespace SzentirasHu\Http\Controllers;

use Illuminate\Contracts\View\View;
use SzentirasHu\Models\Guide;

class GuideController extends Controller
{
    public function index(): View
    {
        return view('guides.index', [
            'guides' => Guide::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function show(Guide $guide): View
    {
        abort_unless($guide->is_active, 404);

        return view('guides.show', [
            'guide' => $guide,
        ]);
    }
}
