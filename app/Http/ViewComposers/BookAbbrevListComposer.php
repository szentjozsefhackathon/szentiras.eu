<?php
/**

 */

namespace SzentirasHu\Http\ViewComposers;

use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TranslationService;

class BookAbbrevListComposer
{


    function __construct(protected TranslationService $translationService, protected BookService $bookService)
    {
        
    }

    public function compose($view)
    {
        $translation = array_key_exists('translation', $view->getData()) ?
            $view['translation'] :
            $this->translationService->getDefaultTranslation();
        $books = $this->bookService->getBooksForTranslation($translation);
        $view
            ->with(['books' => $books, 'translation' => $translation]);
    }
}