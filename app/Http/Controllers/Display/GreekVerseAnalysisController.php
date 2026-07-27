<?php

namespace SzentirasHu\Http\Controllers\Display;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\GreekVerseAnalysis;

class GreekVerseAnalysisController extends Controller
{
    public function __invoke(Request $request, string $gepi): Response
    {
        $greekVerse = GreekVerse::query()
            ->where('gepi', $gepi)
            ->firstOrFail();
        $analysis = GreekVerseAnalysis::query()
            ->where('gepi', $greekVerse->gepi)
            ->where('greek_source', $greekVerse->source)
            ->where('locale', GreekVerseAnalysis::DEFAULT_LOCALE)
            ->firstOrFail();

        $response = response()->view('greekText.verseAnalysis', [
            'verseAnalysis' => $analysis->analysis,
            'annotatedWords' => $greekVerse->annotatedWords(),
        ]);
        $response->setEtag(hash('sha256', (string) $response->getContent()));
        $response->setPublic();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->isNotModified($request);

        return $response;
    }
}
