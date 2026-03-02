<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

/**
 * Controller for various tools and utilities
 */
class ToolsController extends Controller
{
    public function __construct(
        protected TextService $textService,
        protected TranslationService $translationService
    ) {
    }

    /**
     * Display the tools index page
     */
    public function index()
    {
        return \View::make("tools/index", [
            'pageTitle' => 'Eszközök - Szentírás.eu',
            'metaTitle' => 'Eszközök - Szentírás.eu'
        ]);
    }

    /**
     * Display the memory game creator tool
     */
    public function memoryGameCreator(Request $request)
    {
        $verses = [];
        $errors = [];
        $input = '';
        $selectedTranslation = null;

        // Get all translations for the dropdown
        $translations = $this->translationService->getAllTranslations();
        
        // Set default translation for initial page load
        if (!$request->isMethod('post')) {
            $defaultTranslation = $this->translationService->getDefaultTranslation();
            $selectedTranslation = $defaultTranslation->abbrev;
        }

        if ($request->isMethod('post')) {
            $input = $request->input('references', '');
            $translationAbbrev = $request->input('translation_abbrev', null);
            
            // Get the selected translation or use default
            if ($translationAbbrev) {
                $translation = $this->translationService->getByAbbreviation($translationAbbrev);
                $selectedTranslation = $translationAbbrev;
            } else {
                $translation = $this->translationService->getDefaultTranslation();
                $selectedTranslation = $translation->abbrev;
            }
            
            // Parse reference lines
            $lines = array_filter(array_map('trim', explode("\n", $input)));
            
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }
                
                try {
                    $canonicalRef = CanonicalReference::fromString($line);
                    $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation);
                    
                    $fullText = '';
                    $reference = '';
                    
                    foreach ($verseContainers as $verseContainer) {
                        foreach ($verseContainer->getParsedVerses() as $verse) {
                            // Get text without headings (false parameter) and strip any remaining HTML tags
                            $text = strip_tags($verse->getText(false));
                            $fullText .= $text . ' ';
                            if (empty($reference)) {
                                $reference = $verse->book->abbrev . " " . $verse->chapter . ',' . $verse->numv;
                            }
                        }
                    }
                    
                    $fullText = trim($fullText);
                    
                    // Skip if no text was found
                    if (empty($fullText)) {
                        $errors[] = "Nem található szöveg: {$line}";
                        continue;
                    }
                    
                    // Split text into two parts at word boundary
                    $words = explode(' ', $fullText);
                    $wordCount = count($words);
                    $halfPoint = (int)($wordCount / 2);
                    
                    $firstHalf = implode(' ', array_slice($words, 0, $halfPoint));
                    $secondHalf = implode(' ', array_slice($words, $halfPoint));
                    
                    $verses[] = [
                        'reference' => $reference,
                        'original_input' => $line,
                        'full_text' => $fullText,
                        'first_half' => $firstHalf,
                        'second_half' => $secondHalf
                    ];
                    
                } catch (ParsingException $e) {
                    $errors[] = "Nem sikerült értelmezni: {$line} - {$e->getMessage()}";
                } catch (\Exception $e) {
                    $errors[] = "Hiba történt: {$line} - {$e->getMessage()}";
                }
            }
        }

        return \View::make("tools/memory-game-creator", [
            'pageTitle' => 'Memóriajáték készítő - Szentírás.eu',
            'metaTitle' => 'Memóriajáték készítő - Szentírás.eu',
            'verses' => $verses,
            'errors' => $errors,
            'input' => $input,
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation
        ]);
    }
}
