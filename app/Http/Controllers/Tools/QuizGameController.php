<?php

namespace SzentirasHu\Http\Controllers\Tools;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Services\Tools\QuizGameService;
use SzentirasHu\Services\Tools\QuizGameAnimals;
use SzentirasHu\Services\Tools\ToolsService;

/**
 * Controller for multiplayer Quiz Game
 */
class QuizGameController extends Controller
{
    public function __construct(
        protected QuizGameService $quizGameService,
        protected ToolsService $toolsService
    ) {
    }
    
    /**
     * Show quiz game home page
     */
    public function index()
    {
        $translations = $this->toolsService->getAllTranslations();
        $defaultTranslation = $this->toolsService->getDefaultTranslation();
        
        return View::make("tools/quiz/index", [
            'pageTitle' => 'Interaktív kvízjáték - Szentírás.eu',
            'metaTitle' => 'Interaktív kvízjáték - Szentírás.eu',
            'translations' => $translations,
            'defaultTranslation' => $defaultTranslation,
        ]);
    }
    
    /**
     * Create new game (Teacher)
     */
    public function createGame(Request $request)
    {
        $validated = $request->validate([
            'total_questions' => 'integer|min:5|max:30',
            'time_limit' => 'integer|min:10|max:60',
            'translation_abbrev' => 'string|max:10',
        ]);
        
        $totalQuestions = $validated['total_questions'] ?? 15;
        $timeLimit = $validated['time_limit'] ?? 15;
        $translationAbbrev = $validated['translation_abbrev'] ?? $this->toolsService->getDefaultTranslation()->abbrev;
        
        // Validate translation exists
        $translation = $this->toolsService->getTranslationByAbbreviation($translationAbbrev);
        if (!$translation) {
            return response()->json([
                'success' => false,
                'error' => 'A megadott fordítás nem található: ' . $translationAbbrev,
            ], 400);
        }
        
        $game = $this->quizGameService->createGame($totalQuestions, $timeLimit, $translationAbbrev);
        
        return response()->json([
            'success' => true,
            'room_code' => $game['room_code'],
            'game' => $game,
        ]);
    }
    
    /**
     * Show teacher/projector view
     */
    public function teacherView(string $roomCode)
    {
        $game = $this->quizGameService->getGame($roomCode);
        
        if (!$game) {
            return redirect()->route('quiz.index')->with('error', 'A játék nem található.');
        }
        
        $joinUrl = route('quiz.player', ['roomCode' => $roomCode]);
        
        return View::make("tools/quiz/teacher", [
            'pageTitle' => 'Kvízjáték - Tanári nézet',
            'metaTitle' => 'Kvízjáték - Tanári nézet',
            'roomCode' => $roomCode,
            'joinUrl' => $joinUrl,
            'game' => $game,
        ]);
    }
    
    /**
     * Show player mobile view
     */
    public function playerView(string $roomCode)
    {
        $game = $this->quizGameService->getGame($roomCode);
        
        if (!$game) {
            return redirect()->route('quiz.index')->with('error', 'A játék nem található.');
        }
        
        return View::make("tools/quiz/player", [
            'pageTitle' => 'Kvízjáték - Játékos',
            'metaTitle' => 'Kvízjáték - Játékos',
            'roomCode' => $roomCode,
            'game' => $game,
        ]);
    }
    
    /**
     * Get available animals for a game
     */
    public function getAvailableAnimals(string $roomCode)
    {
        $game = $this->quizGameService->getGame($roomCode);
        
        if (!$game) {
            return response()->json([
                'success' => false,
                'error' => 'A játék nem található.',
            ], 404);
        }
        
        $availableAnimals = QuizGameAnimals::getAvailableAnimalsForGame($game);
        
        return response()->json([
            'success' => true,
            'animals' => $availableAnimals,
        ]);
    }
    
    /**
     * Join game (Player)
     */
    public function joinGame(Request $request, string $roomCode)
    {
        $validated = $request->validate([
            'animal_id' => 'required|string|max:10',
        ]);
        
        // Get or create session ID
        $sessionId = $request->session()->getId();
        
        Log::info("QuizGame: Player joining - Animal ID: {$validated['animal_id']}, Session ID: {$sessionId}, Room: {$roomCode}");
        
        $game = $this->quizGameService->addPlayer($roomCode, $validated['animal_id'], $sessionId);
        
        if (!$game) {
            return response()->json([
                'success' => false,
                'error' => 'Nem sikerült csatlakozni a játékhoz (az állat lehet, hogy már foglalt).',
            ], 400);
        }
        
        // Store player info in session
        $request->session()->put("quiz_player_{$roomCode}", [
            'animal_id' => $validated['animal_id'],
            'session_id' => $sessionId,
        ]);
        
        Log::info("QuizGame: Player joined successfully - Session ID: {$sessionId}");
        
        return response()->json([
            'success' => true,
            'game' => $game,
            'session_id' => $sessionId,
        ]);
    }
    
    /**
     * Start game (Teacher)
     */
    public function startGame(string $roomCode)
    {
        // Get current game state for debugging
        $currentGame = $this->quizGameService->getGame($roomCode);
        
        if (!$currentGame) {
            return response()->json([
                'success' => false,
                'error' => 'Játék nem található.',
                'debug' => [
                    'room_code' => $roomCode,
                    'cache_key' => 'quiz_game:' . $roomCode,
                ]
            ], 400);
        }
        
        if ($currentGame['status'] !== 'waiting') {
            return response()->json([
                'success' => false,
                'error' => 'A játék nem "waiting" állapotban van.',
                'debug' => [
                    'room_code' => $roomCode,
                    'current_status' => $currentGame['status'],
                    'expected_status' => 'waiting',
                ]
            ], 400);
        }
        
        $game = $this->quizGameService->startGame($roomCode);
        
        if (!$game) {
            return response()->json([
                'success' => false,
                'error' => 'A startGame() null-t adott vissza.',
                'debug' => [
                    'room_code' => $roomCode,
                    'game_before_start' => $currentGame,
                ]
            ], 400);
        }
                
        return response()->json([
            'success' => true,
            'game' => $game,
        ]);
    }
    
    /**
     * Submit answer (Player)
     */
    public function submitAnswer(Request $request, string $roomCode)
    {
        $validated = $request->validate([
            'option_index' => 'required|integer|min:0|max:3',
            'response_time_ms' => 'required|integer|min:0',
        ]);
        
        $sessionId = $request->session()->getId();
        
        $currentGame = $this->quizGameService->getGame($roomCode);
        
        $game = $this->quizGameService->submitAnswer(
            $roomCode,
            $sessionId,
            $validated['option_index'],
            $validated['response_time_ms']
        );
        
        if (!$game) {
            return response()->json([
                'success' => false,
                'error' => 'Nem sikerült elküldeni a választ.',
                'debug' => [
                    'room_code' => $roomCode,
                    'session_id' => $sessionId,
                    'option_index' => $validated['option_index'],
                    'game_status' => $currentGame['status'] ?? 'not found',
                    'game_exists' => $currentGame !== null,
                    'players_count' => count($currentGame['players'] ?? []),
                    'players' => array_map(fn($p) => ['animal_name' => $p['animal_name'], 'animal_svg_url' => $p['animal_svg_url'], 'session_id' => $p['session_id']], $currentGame['players'] ?? []),
                ]
            ], 400);
        }
                
        return response()->json([
            'success' => true,
            'game' => $game,
        ]);
    }
    
    /**
     * Show results and move to next question (Teacher)
     */
    public function showResults(string $roomCode)
    {
        $game = $this->quizGameService->nextQuestion($roomCode);
        
        if (!$game) {
            return response()->json([
                'success' => false,
                'error' => 'Hiba történt.',
            ], 400);
        }
        
        // Get answer statistics
        $stats = $this->quizGameService->getAnswerStats($roomCode);
        $leaderboard = $this->quizGameService->getLeaderboard($roomCode);
                
        return response()->json([
            'success' => true,
            'game' => $game,
            'stats' => $stats,
            'leaderboard' => $leaderboard,
        ]);
    }
    
    /**
     * Continue to next question (Teacher)
     */
    public function nextQuestion(string $roomCode)
    {
        $game = $this->quizGameService->continueToNextQuestion($roomCode);
        
        if (!$game) {
            return response()->json([
                'success' => false,
                'error' => 'Hiba történt.',
            ], 400);
        }
                
        return response()->json([
            'success' => true,
            'game' => $game,
        ]);
    }
    
    /**
     * Get current game state (for polling/updates)
     */
    public function getGameState(string $roomCode)
    {
        $game = $this->quizGameService->getGame($roomCode);
        
        if (!$game) {
            return response()->json([
                'success' => false,
                'error' => 'Játék nem található.',
            ], 404);
        }
        
        $currentQuestion = $this->quizGameService->getCurrentQuestion($roomCode);
        $stats = $this->quizGameService->getAnswerStats($roomCode);
        $leaderboard = $this->quizGameService->getLeaderboard($roomCode);
        
        return response()->json([
            'success' => true,
            'game' => $game,
            'current_question' => $currentQuestion,
            'answer_stats' => $stats,
            'leaderboard' => $leaderboard,
        ]);
    }
    
    /**
     * End game (Teacher)
     */
    public function endGame(string $roomCode)
    {
        $this->quizGameService->deleteGame($roomCode);
                
        return response()->json([
            'success' => true,
        ]);
    }
}
