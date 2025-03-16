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

    public function __construct(HttpRequest $request) {
        $this->headings = $request->input('headings', 'true') == 'true';
        $this->nums = $request->input('nums', 'false') == 'true';
        $this->refs = $request->input('refs', 'false') == 'true';
        $this->quantity = $request->input('quantity', 1);
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
        $verses = $this->textService->getTranslatedVerses($ref, $this->translationRepository->getById($translationId));
        return view('textDisplay.pdf.print' , ['verses' => $verses, 'options' => $options, 'reference' => $ref]);
        }

} 