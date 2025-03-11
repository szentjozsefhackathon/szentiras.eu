<?php

namespace SzentirasHu\Http\Controllers\Search;

use Config;
use Illuminate\Http\Request;
use SzentirasHu\Data\Entity\EmbeddedExcerptScope;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Http\Controllers\Search\SearchForm;
use SzentirasHu\Http\Controllers\Search\SemanticSearchForm;
use SzentirasHu\Http\Requests\SemanticSearchFormRequest;
use SzentirasHu\Rules\TurnstileValidationRule;
use SzentirasHu\Service\Search\SemanticSearchParams;
use SzentirasHu\Service\Search\SemanticSearchService;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;
use View;

class SemanticSearchController extends Controller
{
    
    public function __construct(
        protected SemanticSearchService $semanticSearchService, 
        protected TranslationService $translationService, 
        protected BookService $bookService)
    {

    }

    public function anySearch(SemanticSearchFormRequest $request)
    {
        $textToSearch = $request->get('textToSearchAi');
        if (empty($textToSearch)) {
            return $this->getIndex($request);
        }
        $form = $this->prepareForm($request, $textToSearch);

        if (!session()->get('anonymous_token') && !$form->captchaValidated) {
            // validate captcha
            $request->validate([
                'cf-turnstile-response' => ['required', new TurnstileValidationRule()],
            ]);

            if (Config::get('settings.ai.unregisteredSearchLimit') != -1) {
                $key = 'semanticSearchCalls';
                $count = $request->session()->get($key, 0) + 1;
                if ($count > Config::get('settings.ai.unregisteredSearchLimit')) {
                    return view('search.semanticSearchThrottle');
                }
                $request->session()->put($key, $count);            
            }        
        }
        
        $form->captchaValidated = true;
        $view = $this->getView($form);
        $view = $this->semanticSearch($form, $view);
        return $view;
    }

    public function getIndex(Request $request)
    {
        return $this->getView($this->prepareForm($request));
    }

    private function getView($form)
    {
        $translations = $this->translationService->getAllTranslations();
        $books = $this->bookService->getBooksForTranslation($this->translationService->getDefaultTranslation());
        return View::make("search.semanticSearch", [
            'form' => $form,
            'translations' => $translations,
            'books' => $books,
        ]);
    }


    private function prepareForm($request) : SemanticSearchForm
    {
        $form = new SemanticSearchForm();
        $form->textToSearchAi = $request->get('textToSearchAi');
        $form->usxCode =  $request->get('usxCode');
        $form->captchaValidated =  $request->get('captchaValidated');
                
        if ($request->get('translationAbbrev') != '0') {
            $form->translationAbbrev = $request->get('translationAbbrev');
        }

        return $form;
    }

    private function groupResponses($results) {
        $groupedResponses = [];
        foreach ($results as $item) {
            $embeddedExcerpt = $item->embeddedExcerpt;
            if ($embeddedExcerpt->scope == EmbeddedExcerptScope::Verse) {
                $gepi = $embeddedExcerpt->gepi;
            } else if ($embeddedExcerpt->scope == EmbeddedExcerptScope::Chapter) {
                $gepi = "{$embeddedExcerpt->usx_code}_{$embeddedExcerpt->chapter}";
            } else {
                $gepi = "{$embeddedExcerpt->usx_code}_{$embeddedExcerpt->chapter}_{$embeddedExcerpt->verse}_{$embeddedExcerpt->to_chapter}_{$embeddedExcerpt->to_verse}";
            }
            if (!isset($groupedResponses[$gepi])) {
                $groupedResponses[$gepi] = [ 'items' => [], 'totalDistance' => 0, 'count' => 0];
            }
            $groupedResponses[$gepi]['items'][] = $item;
            $groupedResponses[$gepi]['totalDistance'] += $item->distance;
            $groupedResponses[$gepi]['count']++;
        }

        foreach ($groupedResponses as $gepi => $data) {
            $groupedResponses[$gepi]['averageDistance'] = $data['totalDistance'] / $data['count'];
            $groupedResponses[$gepi]['quality'] = SemanticSearchService::getQualityScore($groupedResponses[$gepi]['averageDistance'] );            
        }
        $groupedResponses = array_sort($groupedResponses, function ($value) {
            return $value['averageDistance'];
        });
        return $groupedResponses;
    }

    private function semanticSearch(SemanticSearchForm $form, $view)
    {
        $semanticSearchParams = new SemanticSearchParams();
        $semanticSearchParams->text = $form->textToSearchAi;
        $semanticSearchParams->translationAbbrev = $form->translationAbbrev;
        $semanticSearchParams->usxCodes = array_keys(SearchController::extractBookUsxCodes($form->usxCode));
        $aiResult = $this->semanticSearchService->generateVector($form->textToSearchAi);
        $response = $this->semanticSearchService->findNeighbors($semanticSearchParams, $aiResult->vector, EmbeddedExcerptScope::Verse, 25);
        $chapterResponse = $this->semanticSearchService->findNeighbors($semanticSearchParams, $aiResult->vector, EmbeddedExcerptScope::Chapter);
        $rangeResponse = $this->semanticSearchService->findNeighbors($semanticSearchParams, $aiResult->vector, EmbeddedExcerptScope::Range);

        $view = $view->with('groupedResponses', $this->groupResponses($response->results));
        $view = $view->with('groupedRangeResponses', $this->groupResponses($rangeResponse->results));
        $view = $view->with('groupedChapterResponses', $this->groupResponses($chapterResponse->results));

        return $view;
    }


}
