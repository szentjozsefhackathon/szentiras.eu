<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Services\Tools\WordFromNextVerseService;
use SzentirasHu\Services\Tools\ToolsService;

/**
 * Controller for Word from Next Verse game
 */
class WordFromNextVerseController extends Controller
{
    public function __construct(
        protected WordFromNextVerseService $wordFromNextVerseService,
        protected ToolsService $toolsService
    ) {
    }

    public function index(Request $request)
    {
        $translations = $this->toolsService->getAllTranslations();
        $selectedTranslation = null;
        
        // Reset game if requested
        if ($request->has('reset')) {
            $request->session()->forget('word_from_next_verse_state');
            return redirect('/tools/word-from-next-verse');
        }
        
        // Get game state first to check if we have an active game
        $gameState = $request->session()->get('word_from_next_verse_state', null);
        
        // Set translation - if new game is starting, use request param or default
        // Otherwise, prefer active game's translation
        if ($request->input('action') === 'new_game' || !$gameState) {
            // New game starting - allow translation selection
            if ($request->has('translation_abbrev')) {
                $selectedTranslation = $request->input('translation_abbrev');
            } else {
                $defaultTranslation = $this->toolsService->getDefaultTranslation();
                $selectedTranslation = $defaultTranslation->abbrev;
            }
        } elseif ($gameState && isset($gameState['translation'])) {
            // Active game - use session translation unless explicitly changing
            if ($request->has('translation_abbrev') && $request->input('translation_abbrev') !== $gameState['translation']) {
                // User wants to change translation - treat as new game
                $selectedTranslation = $request->input('translation_abbrev');
                $gameState = null; // Force new game generation
            } else {
                $selectedTranslation = $gameState['translation'];
            }
        } elseif ($request->has('translation_abbrev')) {
            $selectedTranslation = $request->input('translation_abbrev');
        } else {
            $defaultTranslation = $this->toolsService->getDefaultTranslation();
            $selectedTranslation = $defaultTranslation->abbrev;
        }

        $translation = $this->toolsService->getTranslationByAbbreviation($selectedTranslation);
        $books = $this->toolsService->getBooksForTranslation($translation);
        
        // Initialize feedback variables
        $feedback = null;
        $correct = false;
        
        // If page is accessed via GET and question was answered, generate new question automatically
        if (!$request->isMethod('post') && $gameState && !empty($gameState['answered'])) {
            $request->merge(['action' => 'next_question']);
        }
        
        // Start new game or generate new question
        if ($request->input('action') === 'new_game' || $request->input('action') === 'next_question' 
            || !$gameState || ($request->has('translation_abbrev') && $gameState['translation'] !== $selectedTranslation)) {
            
            $questionData = $this->wordFromNextVerseService->generateQuestion($books, $translation);
            
            if ($questionData) {
                $gameState = [
                    'currentVerse' => $questionData['currentVerse'],
                    'currentReference' => $questionData['currentReference'],
                    'nextVerse' => $questionData['nextVerse'],
                    'nextReference' => $questionData['nextReference'],
                    'correctWord' => $questionData['correctWord'],
                    'options' => $questionData['options'],
                    'translation' => $selectedTranslation,
                    'score' => $request->input('action') === 'next_question' ? ($gameState['score'] ?? 0) : 0,
                    'answered' => false
                ];
                $request->session()->put('word_from_next_verse_state', $gameState);
            }
        }
        
        // Check answer
        if ($request->input('action') === 'answer' && $gameState && !$gameState['answered']) {
            $userAnswer = $request->input('selected_word');
            
            if ($userAnswer === $gameState['correctWord']) {
                $feedback = 'Helyes! 🎉';
                $correct = true;
                $gameState['score'] = ($gameState['score'] ?? 0) + 1;
            } else {
                $feedback = 'Helytelen! A helyes válasz: ' . $gameState['correctWord'];
                $correct = false;
            }
            
            $gameState['answered'] = true;
            $request->session()->put('word_from_next_verse_state', $gameState);
        }
        
        return \View::make("tools/word-from-next-verse", [
            'pageTitle' => 'Szó a következő versből - Szentírás.eu',
            'metaTitle' => 'Szó a következő versből - Szentírás.eu',
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'currentVerse' => $gameState['currentVerse'] ?? null,
            'currentReference' => $gameState['currentReference'] ?? null,
            'nextReference' => $gameState['nextReference'] ?? null,
            'options' => $gameState['options'] ?? [],
            'correctWord' => ($gameState && !empty($gameState['answered'])) ? $gameState['correctWord'] : null,
            'feedback' => $feedback,
            'correct' => $correct,
            'score' => $gameState['score'] ?? 0,
            'answered' => $gameState['answered'] ?? false
        ]);
    }
}
