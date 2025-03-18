<?php
/**

 */

namespace SzentirasHu\Http\Controllers\Display;


use Illuminate\Http\Request as HttpRequest;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Data\Repository\TranslationRepository;
use \View;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Text\TextService;

class PdfOptions {
    public $headings = true;
    public $refs = false;
    public $nums = false;
    public $quantity = 1;
    public $fullContext = false;

    public function __construct(HttpRequest $request) {
        $this->headings = $request->input('headings', 'true') == 'true';
        $this->nums = $request->input('nums', 'false') == 'true';
        $this->refs = $request->input('refs', 'false') == 'true';
        $this->quantity = $request->input('quantity', 1);
        $this->fullContext = $request->input('fullContext', 'false') == 'true';
    }

}

class PdfController extends Controller {

    /**
     * @var TextService
     */
    private $textService;
    /**
     * @var \SzentirasHu\Data\Repository\TranslationRepository
     */
    private $translationRepository;

    function __construct(TextService $textService, TranslationRepository $translationRepository)
    {
        $this->textService = $textService;
        $this->translationRepository = $translationRepository;
    }

    public function getDialog($translationAbbrev, $refString) {
        return View::make('textDisplay.pdf.pdfDialog')->with([ 'refString' => $refString, 'translationId' => $this->translationRepository->getByAbbrev($translationAbbrev)->id]);
    }

    public function getRef($translationId, $refString)
    {
        $options = new PdfOptions(request());
        $ref = CanonicalReference::fromString($refString);
        $translation = $this->translationRepository->getById($translationId);
        $verseContainers = $this->textService->getTranslatedVerses($ref, $translation);        
        // XXX: Duplicate code from TextDisplayController
        if ($options->fullContext) {
            // Collect chapter numbers from verse containers
            $chapterNumbers = [];
            foreach ($verseContainers as $verseContainer) {
                $chapterNumbers[$verseContainer->bookRef->bookId] = array_merge($verseContainer->bookRef->getIncludedChapters(), $chapterNumbers[$verseContainer->bookRef->bookId] ?? []);
                $chapterNumbers[$verseContainer->bookRef->bookId] = array_unique($chapterNumbers[$verseContainer->bookRef->bookId]);
                // sort the array
                sort($chapterNumbers[$verseContainer->bookRef->bookId]);
            }
            // Create a new canonical reference with the collected chapter numbers
            $chapterReferenceString = '';
            foreach ($chapterNumbers as $bookId => $chapters) {
                // ["Mt" => [1,2], "Mk" => [2,3]] should be "Mt1;2;3;Mk3"
                $chapterReferenceString .= $bookId;
                $chapterReferenceString .= implode(';', $chapters);
            }
            $fullContextVerseContainers = $this->textService->getTranslatedVerses(CanonicalReference::fromString($chapterReferenceString, $translation->id), $translation);
            $highlightedGepis = [];
            foreach ($verseContainers as $verseContainer) {
                $highlightedGepis = array_merge($highlightedGepis, array_map(fn($k) => "{$k}", array_keys($verseContainer->rawVerses)));
            }
            $verses = $fullContextVerseContainers;
        } else {
            $verses = $verseContainers;
        }
        return view('textDisplay.pdf.print' , ['verses' => $verses, 'options' => $options, 'reference' => $ref]);
        }

} 