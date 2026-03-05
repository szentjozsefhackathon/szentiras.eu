<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Text\BookService;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\Text\TranslationService;

/**
 * Controller for various tools and utilities
 */
class ToolsController extends Controller
{
    public function __construct(
        protected TextService $textService,
        protected TranslationService $translationService,
        protected BookService $bookService
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
                // skip empty lines
                if (empty($line)) {
                    continue;
                }
                try {
                    $canonicalRef = CanonicalReference::fromString($line);
                    $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation);
                    $count = array_sum(array_map(fn($vc) => count($vc->rawVerses), $verseContainers));
                    // Check if more than 5 verses are included in this reference
                    if ($count > 5) {
                        $errors[] = "Legfeljebb 5 vers adható meg. A '{$line}' referencia " . $count . " versből áll.";
                        continue;
                    }
                    
                    $fullText = '';
                    $reference = '';
                    
                    foreach ($verseContainers as $verseContainer) {
                        foreach ($verseContainer->getParsedVerses() as $verse) {
                            // Get text without headings ('none' parameter) and strip any remaining HTML tags
                            $text = strip_tags($verse->getText('none'));
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
                    
                    // Normalize card text: capitalize first letter and remove trailing punctuation
                    $firstHalf = $this->normalizeCardText($firstHalf);
                    $secondHalf = $this->normalizeCardText($secondHalf);
                    
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

    /**
     * Display the guess book tool (bibDLE game)
     */
    public function guessBook(Request $request)
    {
        $translations = $this->translationService->getAllTranslations();
        $selectedTranslation = null;
        
        // Set default translation for initial page load
        if (!$request->isMethod('post') || !$request->has('translation_abbrev')) {
            $defaultTranslation = $this->translationService->getDefaultTranslation();
            $selectedTranslation = $defaultTranslation->abbrev;
        } else {
            $selectedTranslation = $request->input('translation_abbrev');
        }

        $translation = $this->translationService->getByAbbreviation($selectedTranslation);
        $books = $this->bookService->getBooksForTranslation($translation);
        
        // Initialize or get game state from session
        $gameState = $request->session()->get('guess_book_state', null);
        $guesses = [];
        $won = false;
        
        // Start new game
        if ($request->input('action') === 'new_game' || !$gameState || $gameState['translation'] !== $selectedTranslation) {
            $randomBook = $books->random();
            $versesData = $this->getRandomVersesFromBook($randomBook, $translation);
            
            if ($versesData) {
                $gameState = [
                    'book' => [
                        'id' => $randomBook->id,
                        'name' => $randomBook->name,
                        'abbrev' => $randomBook->abbrev,
                        'testament' => $randomBook->old_testament ? 'old' : 'new',
                        'section' => $this->getBookSection($randomBook->order, $randomBook->old_testament),
                        'firstLetter' => mb_substr($randomBook->name, 0, 1, 'UTF-8')
                    ],
                    'verses' => $versesData['text'],
                    'translation' => $selectedTranslation,
                    'guesses' => []
                ];
                $request->session()->put('guess_book_state', $gameState);
            }
        }
        
        // Process guess
        if ($request->input('action') === 'guess' && $request->has('book_id')) {
            $guessedBookId = (int)$request->input('book_id');
            $guessedBook = $books->firstWhere('id', $guessedBookId);
            
            if ($guessedBook) {
                $guess = [
                    'book' => [
                        'id' => $guessedBook->id,
                        'name' => $guessedBook->name,
                        'abbrev' => $guessedBook->abbrev,
                        'testament' => $guessedBook->old_testament ? 'old' : 'new',
                        'section' => $this->getBookSection($guessedBook->order, $guessedBook->old_testament),
                        'firstLetter' => mb_substr($guessedBook->name, 0, 1, 'UTF-8')
                    ],
                    'matches' => [
                        'testament' => ($guessedBook->old_testament ? 'old' : 'new') === $gameState['book']['testament'],
                        'section' => $this->getBookSection($guessedBook->order, $guessedBook->old_testament) === $gameState['book']['section'],
                        'firstLetter' => mb_substr($guessedBook->name, 0, 1, 'UTF-8') === $gameState['book']['firstLetter'],
                        'book' => $guessedBook->id === $gameState['book']['id']
                    ]
                ];
                
                $gameState['guesses'][] = $guess;
                $request->session()->put('guess_book_state', $gameState);
                
                if ($guess['matches']['book']) {
                    $won = true;
                }
            }
        }
        
        $guesses = $gameState['guesses'] ?? [];
        
        return \View::make("tools/guess-book", [
            'pageTitle' => 'Találd ki a könyvet - Szentírás.eu',
            'metaTitle' => 'Találd ki a könyvet - Szentírás.eu',
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'books' => $books,
            'verses' => $gameState['verses'] ?? '',
            'guesses' => $guesses,
            'won' => $won,
            'correctBook' => $won ? $gameState['book'] : null
        ]);
    }
    
    /**
     * Get random verses from a book (without chapter/verse numbers and headings)
     */
    private function getRandomVersesFromBook($book, $translation, $minVerses = 2, $maxVerses = 4)
    {
        $maxChapter = $this->bookService->getChapterCount($book, $translation);
        if ($maxChapter === 0) {
            return '';
        }
        
        $randomChapter = rand(1, $maxChapter);
        $maxVerse = $this->bookService->getVerseCount($book, $randomChapter, $translation);
        
        if ($maxVerse === 0) {
            return '';
        }
        
        // Get random consecutive verses
        $verseCount = rand($minVerses, min($maxVerses, $maxVerse));
        $startVerse = rand(1, max(1, $maxVerse - $verseCount + 1));
        $endVerse = $startVerse + $verseCount - 1;
        
        try {
            $refString = "{$book->abbrev} {$randomChapter},{$startVerse}";
            if ($endVerse > $startVerse) {
                $refString .= "-{$endVerse}";
            }
            
            $canonicalRef = CanonicalReference::fromString($refString);
            $verseContainers = $this->textService->getTranslatedVerses($canonicalRef, $translation);
            
            $fullText = '';
            foreach ($verseContainers as $verseContainer) {
                foreach ($verseContainer->getParsedVerses() as $verse) {
                    // Get text without headings and strip HTML tags
                    $text = strip_tags($verse->getText('none'));
                    $fullText .= $text . ' ';
                }
            }
            
            return [
                'text' => trim($fullText),
                'reference' => $refString
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get the section of a book based on its order and testament
     */
    private function getBookSection($order, $oldTestament)
    {
        if ($oldTestament) {
            // Old Testament: orders 101-146 (normalized to 1-46)
            $normalizedOrder = $order >= 100 ? $order - 100 : $order;
            
            if ($normalizedOrder >= 1 && $normalizedOrder <= 5) {
                return 'Tóra';
            } elseif ($normalizedOrder >= 6 && $normalizedOrder <= 17) {
                return 'Történelmi könyvek';
            } elseif ($normalizedOrder >= 18 && $normalizedOrder <= 22) {
                return 'Bölcsességi könyvek';
            } elseif ($normalizedOrder >= 23 && $normalizedOrder <= 39) {
                return 'Próféták';
            } else {
                return 'Deuterokanonikus könyvek';
            }
        } else {
            // New Testament: orders 201-227 (normalized to 1-27)
            $normalizedOrder = $order >= 200 ? $order - 200 : $order;
            
            if ($normalizedOrder >= 1 && $normalizedOrder <= 4) {
                return 'Evangéliumok';
            } elseif ($normalizedOrder === 5) {
                return 'Apostolok Cselekedetei';
            } elseif ($normalizedOrder >= 6 && $normalizedOrder <= 19) {
                return 'Pál levelei';
            } elseif ($normalizedOrder >= 20 && $normalizedOrder <= 26) {
                return 'Katolikus levelek';
            } elseif ($normalizedOrder === 27) {
                return 'Jelenések könyve';
            }
        }
        
        return 'Egyéb';
    }
    
    /**
     * Online memory game - play in browser
     */
    public function memoryGamePlay(Request $request)
    {
        $translations = $this->translationService->getAllTranslations();
        $selectedTranslation = null;
        $cards = [];
        $errors = [];
        
        // Set default translation
        if (!$request->isMethod('post') || !$request->has('translation_abbrev')) {
            $defaultTranslation = $this->translationService->getDefaultTranslation();
            $selectedTranslation = $defaultTranslation->abbrev;
        } else {
            $selectedTranslation = $request->input('translation_abbrev');
        }
        
        // Generate cards on POST
        if ($request->isMethod('post') && $request->input('action') === 'generate') {
            $rows = (int)$request->input('rows', 2);
            $cols = (int)$request->input('cols', 3);
            
            // Validate rows and cols
            if ($rows < 2) {
                $errors[] = 'A sorok száma legalább 2 legyen.';
            }
            if ($cols < 2) {
                $errors[] = 'Az oszlopok száma legalább 2 legyen.';
            }
            
            $totalCards = $rows * $cols;
            if ($totalCards % 2 !== 0) {
                $errors[] = 'A kártyák száma (' . $totalCards . ') nem páros. Kérem válasszon olyan sort és oszlopot, amelyek szorzata páros!';
            }
            
            if (empty($errors)) {
                $translation = $this->translationService->getByAbbreviation($selectedTranslation);
                $books = $this->bookService->getBooksForTranslation($translation);
                $pairsNeeded = $totalCards / 2;
                
                // Generate random verses
                $attempts = 0;
                $maxAttempts = 50 + $pairsNeeded; // Prevent infinite loop, allow some retries for short verses
                while (count($cards) < $pairsNeeded && $attempts < $maxAttempts) {
                    $attempts++;
                    $randomBook = $books->random();
                    $versesData = $this->getRandomVersesFromBook($randomBook, $translation, 1, 2);
                    
                    if (!$versesData || empty($versesData['text'])) {
                        continue;
                    }
                    
                    $verseText = $versesData['text'];
                    
                    // Split verse into two halves
                    $words = explode(' ', $verseText);
                    $wordCount = count($words);
                    
                    if ($wordCount < 6) {
                        continue; // Too short
                    }
                    
                    $halfPoint = (int)($wordCount / 2);
                    $firstHalf = implode(' ', array_slice($words, 0, $halfPoint));
                    $secondHalf = implode(' ', array_slice($words, $halfPoint));
                    
                    // Normalize card text: capitalize first letter and remove trailing punctuation
                    $firstHalf = $this->normalizeCardText($firstHalf);
                    $secondHalf = $this->normalizeCardText($secondHalf);
                    
                    $cards[] = [
                        'firstHalf' => $firstHalf,
                        'secondHalf' => $secondHalf,
                        'pairId' => count($cards)
                    ];
                }
                
                if (count($cards) < $pairsNeeded) {
                    $errors[] = 'Nem sikerült elegendő verset találni. Próbálja meg kevesebb kártyával.';
                    $cards = [];
                }
            }
        }
        
        return \View::make("tools/memory-game-play", [
            'pageTitle' => 'Online memóriajáték - Szentírás.eu',
            'metaTitle' => 'Online memóriajáték - Szentírás.eu',
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'cards' => $cards,
            'errors' => $errors,
            'rows' => $request->input('rows', 2),
            'cols' => $request->input('cols', 3)
        ]);
    }
    
    /**
     * Normalize card text: capitalize first letter and remove trailing punctuation
     */
    private function normalizeCardText(string $text): string
    {
        // Remove trailing punctuation marks
        $text = rtrim($text, '.,;:!?"\'…');
        
        // Capitalize first letter (UTF-8 safe)
        $text = mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
        
        return $text;
    }
    
    /**
     * Guess the missing word game (Wordle-like)
     */
    public function guessWord(Request $request)
    {
        $translations = $this->translationService->getAllTranslations();
        $selectedTranslation = null;
        
        // Set default translation for initial page load
        if (!$request->isMethod('post') || !$request->has('translation_abbrev')) {
            $defaultTranslation = $this->translationService->getDefaultTranslation();
            $selectedTranslation = $defaultTranslation->abbrev;
        } else {
            $selectedTranslation = $request->input('translation_abbrev');
        }

        $translation = $this->translationService->getByAbbreviation($selectedTranslation);
        $books = $this->bookService->getBooksForTranslation($translation);
        
        // Initialize or get game state from session
        $gameState = $request->session()->get('guess_word_state', null);
        $guesses = [];
        $won = false;
        $maxGuesses = 6;
        
        // Start new game
        if ($request->input('action') === 'new_game' || !$gameState || $gameState['translation'] !== $selectedTranslation) {
            $randomBook = $books->random();
            $versesData = $this->getRandomVersesFromBook($randomBook, $translation, 3, 4);
            
            if ($versesData && !empty($versesData['text'])) {
                // Find a word with at least 4 letters
                $wordData = $this->selectWordFromText($versesData['text']);
                
                if ($wordData) {
                    $gameState = [
                        'verses' => $versesData['text'],
                        'reference' => $versesData['reference'],
                        'versesWithGap' => $wordData['textWithGap'],
                        'word' => $wordData['word'],
                        'wordNormalized' => $wordData['wordNormalized'],
                        'translation' => $selectedTranslation,
                        'guesses' => []
                    ];
                    $request->session()->put('guess_word_state', $gameState);
                }
            }
        }
        
        // Process guess
        if ($request->input('action') === 'guess' && $request->has('guess_word') && $gameState) {
            $guessInput = $request->input('guess_word');
            $guessNormalized = $this->normalizeWord($guessInput);
            
            if (mb_strlen($guessNormalized) === mb_strlen($gameState['wordNormalized'])) {
                $evaluation = $this->evaluateGuess($guessNormalized, $gameState['wordNormalized']);
                
                // Split normalized word into characters array
                $chars = preg_split('//u', $guessNormalized, -1, PREG_SPLIT_NO_EMPTY);
                
                $guess = [
                    'word' => $guessInput,
                    'wordNormalized' => $guessNormalized,
                    'chars' => $chars,
                    'evaluation' => $evaluation
                ];
                
                $gameState['guesses'][] = $guess;
                $request->session()->put('guess_word_state', $gameState);
                
                // Check if won
                if ($guessNormalized === $gameState['wordNormalized']) {
                    $won = true;
                }
            }
        }
        
        // Process give up
        $gaveUp = false;
        if ($request->input('action') === 'give_up' && $gameState) {
            $gaveUp = true;
        }
        
        $guesses = $gameState['guesses'] ?? [];
        $gameOver = $won || count($guesses) >= $maxGuesses || $gaveUp;
        
        return \View::make("tools/guess-word", [
            'pageTitle' => 'Találd ki a hiányzó szót - Szentírás.eu',
            'metaTitle' => 'Találd ki a hiányzó szót - Szentírás.eu',
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'versesWithGap' => $gameState['versesWithGap'] ?? '',
            'reference' => $gameState['reference'] ?? null,
            'wordLength' => isset($gameState['wordNormalized']) ? mb_strlen($gameState['wordNormalized']) : 0,
            'guesses' => $guesses,
            'won' => $won,
            'gaveUp' => $gaveUp,
            'gameOver' => $gameOver,
            'maxGuesses' => $maxGuesses,
            'correctWord' => $gameOver && !$won ? $gameState['word'] : null
        ]);
    }
    
    /**
     * Select a word from text (at least 4 letters, no punctuation)
     */
    private function selectWordFromText(string $text): ?array
    {
        // Remove punctuation and split into words
        $cleanText = preg_replace('/[^\p{L}\s]/u', '', $text);
        $words = array_filter(explode(' ', $cleanText), function($word) {
            return mb_strlen(trim($word)) >= 4;
        });
        
        if (empty($words)) {
            return null;
        }
        
        // Select a random word
        $wordsArray = array_values($words);
        $selectedWord = $wordsArray[array_rand($wordsArray)];
        $selectedWord = trim($selectedWord);
        
        // Create text with gap (use underlined placeholder)
        $wordLength = mb_strlen($selectedWord);
        $placeholder = '<span class="word-gap">' . str_repeat('_', $wordLength) . '</span>';
        $textWithGap = str_replace($selectedWord, $placeholder, $text);
        
        return [
            'word' => $selectedWord,
            'wordNormalized' => $this->normalizeWord($selectedWord),
            'textWithGap' => $textWithGap
        ];
    }
    
    /**
     * Normalize word: lowercase and remove punctuation
     */
    private function normalizeWord(string $word): string
    {
        // Remove punctuation
        $word = preg_replace('/[^\p{L}]/u', '', $word);
        // Convert to lowercase (UTF-8 safe)
        return mb_strtolower($word, 'UTF-8');
    }
    
    /**
     * Evaluate a guess against the target word (Wordle-like)
     * Returns array of letter evaluations: 'correct', 'present', 'absent'
     */
    private function evaluateGuess(string $guess, string $target): array
    {
        $evaluation = [];
        $guessChars = preg_split('//u', $guess, -1, PREG_SPLIT_NO_EMPTY);
        $targetChars = preg_split('//u', $target, -1, PREG_SPLIT_NO_EMPTY);
        $targetCharCount = array_count_values($targetChars);
        $usedTargetChars = array_fill(0, count($targetChars), false);
        
        // First pass: mark correct positions (green)
        foreach ($guessChars as $i => $char) {
            if ($char === $targetChars[$i]) {
                $evaluation[$i] = 'correct';
                $usedTargetChars[$i] = true;
                $targetCharCount[$char]--;
            } else {
                $evaluation[$i] = null; // Will be determined in second pass
            }
        }
        
        // Second pass: mark present but wrong position (yellow)
        foreach ($guessChars as $i => $char) {
            if ($evaluation[$i] === null) {
                if (isset($targetCharCount[$char]) && $targetCharCount[$char] > 0) {
                    $evaluation[$i] = 'present';
                    $targetCharCount[$char]--;
                } else {
                    $evaluation[$i] = 'absent';
                }
            }
        }
        
        return $evaluation;
    }
}

