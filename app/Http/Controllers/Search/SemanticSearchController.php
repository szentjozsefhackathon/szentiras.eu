<?php

namespace SzentirasHu\Http\Controllers\Search;

use Config;
use Illuminate\Http\JsonResponse;
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
use Symfony\Component\HttpFoundation\StreamedResponse;
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

        if (! $this->admit($request, $form)) {
            return view('search.semanticSearchThrottle');
        }

        $form->captchaValidated = true;
        $view = $this->getView($form);
        $view = $this->semanticSearch($form, $view);
        return $view;
    }

    /**
     * The search of the page's script: answered as newline-delimited JSON events, a "step" event
     * as each step starts and a "result" event with the new content of the page, so the visitor
     * sees what the search is doing. The captcha and the per-session limit are checked before the
     * stream starts, because the session is saved by then.
     */
    public function streamSearch(SemanticSearchFormRequest $request): JsonResponse|StreamedResponse
    {
        $form = $this->prepareForm($request);
        $passageUrl = $this->passageUrl((string) $form->textToSearchAi, $form->translationAbbrev);
        if ($passageUrl !== null) {
            return response()->json(['event' => 'redirect', 'url' => $passageUrl]);
        }
        if (! $this->admit($request, $form)) {
            return response()->json(['event' => 'result', 'html' => $this->mainHtml($form, ['throttled' => true])]);
        }
        $form->captchaValidated = true;
        $usxCodes = array_keys(SearchController::extractBookUsxCodes($form->usxCode));

        return response()->stream(function () use ($form, $usxCodes): void {
            $this->sendEvent(['event' => 'step', 'step' => 'search']);
            try {
                $passages = $this->magisterSemanticSearchService->candidates($form->textToSearchAi);
            } catch (MagisterUnavailableException) {
                $this->sendEvent(['event' => 'result', 'html' => $this->mainHtml($form, ['magisterUnavailable' => true])]);

                return;
            }
            $this->sendEvent(['event' => 'step', 'step' => 'verses']);
            $hits = $this->magisterSemanticSearchService->hitsOf($passages, $form->translationAbbrev, $usxCodes);
            $this->sendEvent(['event' => 'result', 'html' => $this->mainHtml($form, ['hits' => $hits])]);
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Checks the captcha and counts the search against the per-session limit of anonymous
     * visitors; false once the limit is exceeded.
     */
    private function admit(Request $request, SemanticSearchForm $form): bool
    {
        if (session()->get('anonymous_token')) {
            return true;
        }
        if (! $form->captchaValidated) {
            $request->validate([
                'cf-turnstile-response' => ['required', new TurnstileValidationRule()],
            ]);
        }
        if (Config::get('settings.ai.unregisteredSearchLimit') != -1) {
            $key = 'semanticSearchCalls';
            $count = $request->session()->get($key, 0) + 1;
            if ($count > Config::get('settings.ai.unregisteredSearchLimit')) {
                return false;
            }
            $request->session()->put($key, $count);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function sendEvent(array $event): void
    {
        echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mainHtml(SemanticSearchForm $form, array $data): string
    {
        return View::make('search.semanticSearchMain', array_merge($this->viewData($form), $data))->render();
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
        return View::make("search.semanticSearch", $this->viewData($form));
    }

    /**
     * @return array{form: SemanticSearchForm, translations: mixed, books: mixed}
     */
    private function viewData(SemanticSearchForm $form): array
    {
        return [
            'form' => $form,
            'translations' => $this->translationService->getAllTranslations(),
            'books' => $this->bookService->getBooksForTranslation($this->translationService->getDefaultTranslation()),
        ];
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
