<?php

namespace SzentirasHu\Http\Controllers\Home;

use Illuminate\Support\Carbon;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Models\DailyReading;

/**
 *
 * Controller for the home page.
 * Note that many parts on the home view are coming from view composers.
 */
class HomeController extends Controller
{

    function __construct(protected TranslationRepository $translationRepository)
    {
    }

    public function index()
    {
        $todayReading = DailyReading::whereDate('date', Carbon::today())->first();

        return \View::make("home", [
            'pageTitle' => 'Biblia online – a Szentírás katolikus és protestáns fordításokban',
            'teaser' => 'Olvasd és keresd a Bibliát online, ingyen: a teljes Szentírás magyarul, katolikus és protestáns fordításokban, a görög Újszövetséggel és MI kereséssel.',
            'cathBibles' => $this->translationRepository->getByDenom('katolikus'),
            'otherBibles' => $this->translationRepository->getByDenom('protestáns'),
            'fullQuickSearch' => true,
            'hideLeftColumn' => true,
            'todayDailyReading' => ($todayReading && $todayReading->isAvailable()) ? $todayReading : null,
        ]);
    }

}