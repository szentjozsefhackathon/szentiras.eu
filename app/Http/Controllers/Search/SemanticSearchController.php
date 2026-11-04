<?php

namespace SzentirasHu\Http\Controllers\Search;

use Config;
use Illuminate\Http\Request;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Http\Controllers\Search\SemanticSearchForm;
use SzentirasHu\Http\Requests\SemanticSearchFormRequest;
use SzentirasHu\Rules\TurnstileValidationRule;
use SzentirasHu\Service\Magister\MagisterUnavailableException;
use SzentirasHu\Service\Reference\ReferenceDetector;
use SzentirasHu\Service\Search\MagisterSemanticSearchService;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;
use View;

class SemanticSearchController extends Controller
{

    public function __construct(
        protected MagisterSemanticSearchService $magisterSemanticSearchService,
        protected TranslationService $translationService,
        protected BookService $bookService,
        protected ReferenceDetector $referenceDetector,
        protected BookRepository $bookRepository,
    ) {}

    public function anySearch(SemanticSearchFormRequest $request)
    {
        $textToSearch = $request->get('textToSearchAi');
        if (empty($textToSearch)) {
            return $this->getIndex($request);
        }
        $form = $this->prepareForm($request);
        $passageUrl = $this->passageUrl($textToSearch, $form->translationAbbrev);
        if ($passageUrl !== null) {
            return redirect($passageUrl);
        }

        if (!session()->get('anonymous_token')) {
            if (!$form->captchaValidated) {
                // validate captcha
                $request->validate([
                    'cf-turnstile-response' => ['required', new TurnstileValidationRule()],
                ]);
            }

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
        $form = $this->prepareForm($request);
        $passageUrl = $this->passageUrl((string) $form->textToSearchAi, $form->translationAbbrev);
        if ($passageUrl !== null) {
            return redirect($passageUrl);
        }

        return $this->getView($form);
    }

    /**
     * A typed reference ("Efezus 2,8", "103. zsoltár") is not a question: it goes straight to the
     * passage, in the chosen translation if that has the book, otherwise in the default one.
     */
    private function passageUrl(string $text, ?string $translationAbbrev): ?string
    {
        $detected = $this->referenceDetector->detect($text);
        if ($detected === null) {
            return null;
        }
        $defaultTranslation = $this->translationService->getDefaultTranslation();
        $translation = $this->translationService->getAllTranslations()->firstWhere('abbrev', $translationAbbrev) ?? $defaultTranslation;
        $book = $this->bookRepository->getByUsxCodeForTranslation($detected->usxCode, $translation);
        if ($book === null && $translation->id !== $defaultTranslation->id) {
            $translation = $defaultTranslation;
            $book = $this->bookRepository->getByUsxCodeForTranslation($detected->usxCode, $translation);
        }
        if ($book === null) {
            return null;
        }

        return "/{$translation->abbrev}/{$book->abbrev}{$detected->chapterVerses}";
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


    private function prepareForm($request): SemanticSearchForm
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

    private function semanticSearch(SemanticSearchForm $form, $view)
    {
        $usxCodes = array_keys(SearchController::extractBookUsxCodes($form->usxCode));
        try {
            $hits = $this->magisterSemanticSearchService->search($form->textToSearchAi, $form->translationAbbrev, $usxCodes);
        } catch (MagisterUnavailableException) {
            return $view->with('magisterUnavailable', true);
        }

        return $view->with('hits', $hits);
    }
}
