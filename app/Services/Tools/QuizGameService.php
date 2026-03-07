<?php

namespace SzentirasHu\Services\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SzentirasHu\Services\Tools\QuizGameAnimals;

/**
 * Service for multiplayer Quiz Game
 * Session-based, no database storage
 */
class QuizGameService
{
    private const CACHE_PREFIX = 'quiz_game:';
    private const GAME_DURATION = 7200; // 2 hours max
    
    private const COLORS = [
        ['color' => 'blue', 'hex' => '#007bff', 'icon' => '🔵'],
        ['color' => 'red', 'hex' => '#dc3545', 'icon' => '🔴'],
        ['color' => 'green', 'hex' => '#28a745', 'icon' => '🟢'],
        ['color' => 'yellow', 'hex' => '#ffc107', 'icon' => '🟡'],
    ];
    
    public function __construct(
        protected ToolsService $toolsService
    ) {
    }
    
    /**
     * Create a new game
     */
    public function createGame(int $totalQuestions = 15, int $timeLimit = 15, string $translationAbbrev = 'KNB'): array
    {
        $roomCode = $this->generateRoomCode();
        
        $gameState = [
            'room_code' => $roomCode,
            'status' => 'waiting', // waiting, playing, results, finished
            'current_question' => 0,
            'total_questions' => $totalQuestions,
            'question_time_limit' => $timeLimit,
            'translation_abbrev' => $translationAbbrev,
            'created_at' => now()->toIso8601String(),
            'questions' => [],
            'players' => [],
            'current_answers' => [],
        ];
        
        Cache::put($this->gameKey($roomCode), $gameState, self::GAME_DURATION);
        
        return $gameState;
    }
    
    /**
     * Generate unique room code
     */
    private function generateRoomCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Cache::has($this->gameKey($code)));
        
        return $code;
    }
    
    /**
     * Get game state
     */
    public function getGame(string $roomCode): ?array
    {
        return Cache::get($this->gameKey($roomCode));
    }
    
    /**
     * Add player to game
     */
    public function addPlayer(string $roomCode, string $animalId, string $sessionId): ?array
    {
        Log::info("QuizGame: addPlayer called with room={$roomCode}, animalId={$animalId}, session={$sessionId}");
        
        $game = $this->getGame($roomCode);
        
        if (!$game) {
            Log::warning("QuizGame: Game not found in cache for room: {$roomCode}");
            return null;
        }
        
        Log::info("QuizGame: Game found. Status: {$game['status']}, Players: " . count($game['players']));
        
        if ($game['status'] === 'finished') {
            Log::warning("QuizGame: Cannot join - game is finished");
            return null;
        }
        
        // Get animal data from ID
        $animalData = QuizGameAnimals::getAnimalById($animalId);
        if (!$animalData) {
            Log::warning("QuizGame: Invalid animal ID: {$animalId}");
            return null;
        }
        
        $animalName = $animalData['name'];
        $animalSvgUrl = $animalData['svg_url'];
        
        // Check if player already exists
        $existingPlayerIndex = $this->findPlayerIndex($game, $sessionId);
        
        if ($existingPlayerIndex !== null) {
            // Update existing player
            $game['players'][$existingPlayerIndex]['animal_id'] = $animalId;
            $game['players'][$existingPlayerIndex]['animal_name'] = $animalName;
            $game['players'][$existingPlayerIndex]['animal_svg_url'] = $animalSvgUrl;
            $game['players'][$existingPlayerIndex]['connected'] = true;
            $game['players'][$existingPlayerIndex]['last_seen'] = now()->toIso8601String();
            Log::info("QuizGame: Updated existing player at index {$existingPlayerIndex}");
        } else {
            // Check if animal is already taken by another player
            foreach ($game['players'] as $player) {
                if ($player['animal_id'] === $animalId) {
                    Log::warning("QuizGame: Animal {$animalName} already taken");
                    return null;
                }
            }
            
            // Add new player
            $game['players'][] = [
                'session_id' => $sessionId,
                'animal_id' => $animalId,
                'animal_name' => $animalName,
                'animal_svg_url' => $animalSvgUrl,
                'score' => 0,
                'connected' => true,
                'last_seen' => now()->toIso8601String(),
            ];
            Log::info("QuizGame: Added new player with animal {$animalName}. Total players now: " . count($game['players']));
        }
        
        $this->saveGame($roomCode, $game);
        Log::info("QuizGame: Game saved to cache");
        
        return $game;
    }
    
    /**
     * Start game and generate first question
     */
    public function startGame(string $roomCode): ?array
    {
        $game = $this->getGame($roomCode);
        
        if (!$game) {
            Log::error("QuizGame: startGame - game not found for room: {$roomCode}");
            return null;
        }
        
        if ($game['status'] !== 'waiting') {
            Log::error("QuizGame: startGame - invalid status '{$game['status']}' for room: {$roomCode}");
            return null;
        }
        
        Log::info("QuizGame: Starting game for room {$roomCode}, generating {$game['total_questions']} questions");
        
        // Generate all questions upfront
        $translation = $this->toolsService->getTranslationByAbbreviation($game['translation_abbrev']);
        
        if (!$translation) {
            Log::error("QuizGame: Translation not found: {$game['translation_abbrev']}");
            return null;
        }
        
        $books = $this->toolsService->getBooksForTranslation($translation);
        
        if (!$books || $books->isEmpty()) {
            Log::error("QuizGame: No books found for translation: {$game['translation_abbrev']}");
            return null;
        }
        
        Log::info("QuizGame: Found {$books->count()} books for translation {$game['translation_abbrev']}");
        
        for ($i = 0; $i < $game['total_questions']; $i++) {
            $question = $this->generateQuestion($books, $translation, $i + 1);
            if ($question) {
                $game['questions'][] = $question;
                Log::debug("QuizGame: Generated question " . ($i + 1));
            } else {
                Log::warning("QuizGame: Failed to generate question " . ($i + 1));
            }
        }
        
        if (empty($game['questions'])) {
            Log::error("QuizGame: No questions generated for room {$roomCode}");
            return null;
        }
        
        Log::info("QuizGame: Successfully generated " . count($game['questions']) . " questions for room {$roomCode}");
        
        $game['status'] = 'playing';
        $game['current_question'] = 1;
        $game['question_started_at'] = now()->toIso8601String();
        $game['current_answers'] = [];
        
        $this->saveGame($roomCode, $game);
        
        Log::info("QuizGame: Game started successfully for room {$roomCode}");
        
        return $game;
    }
    
    /**
     * Generate a single question
     */
    private function generateQuestion($books, $translation, int $questionNumber): ?array
    {
        $maxAttempts = 20;
        
        Log::debug("QuizGame: generateQuestion #{$questionNumber}, books count: {$books->count()}");
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $randomBook = $books->random();
            Log::debug("QuizGame: Attempt #{$attempt} for question #{$questionNumber}, selected book: {$randomBook->name}");
            
            $versesData = $this->toolsService->getRandomVersesFromBook($randomBook, $translation, 1, 1);
            
            if (!$versesData || empty($versesData['text'])) {
                Log::debug("QuizGame: No verse data for book {$randomBook->name}");
                continue;
            }
            
            Log::debug("QuizGame: Got verse from {$randomBook->name}: " . substr($versesData['text'], 0, 50));
            
            // Generate wrong options (other books from same testament)
            $wrongBooks = $books->filter(fn($b) => $b->id !== $randomBook->id)
                ->random(min(3, $books->count() - 1));
            
            if ($wrongBooks->count() < 3) {
                Log::debug("QuizGame: Not enough wrong books, only {$wrongBooks->count()}");
                continue;
            }
            
            // Build options array
            $options = [];
            $correctIndex = rand(0, 3);
            
            $wrongBooksArray = $wrongBooks->values()->all();
            $wrongIndex = 0;
            
            for ($i = 0; $i < 4; $i++) {
                if ($i === $correctIndex) {
                    $options[] = [
                        'book_name' => $randomBook->name,
                        'book_abbrev' => $randomBook->abbrev,
                        'color' => self::COLORS[$i]['color'],
                        'hex' => self::COLORS[$i]['hex'],
                        'icon' => self::COLORS[$i]['icon'],
                        'is_correct' => true,
                    ];
                } else {
                    $wrongBook = $wrongBooksArray[$wrongIndex];
                    $options[] = [
                        'book_name' => $wrongBook->name,
                        'book_abbrev' => $wrongBook->abbrev,
                        'color' => self::COLORS[$i]['color'],
                        'hex' => self::COLORS[$i]['hex'],
                        'icon' => self::COLORS[$i]['icon'],
                        'is_correct' => false,
                    ];
                    $wrongIndex++;
                }
            }
            
            return [
                'question_number' => $questionNumber,
                'verse_text' => $versesData['text'],
                'verse_reference' => $versesData['reference'],
                'options' => $options,
                'correct_index' => $correctIndex,
            ];
        }
        
        return null;
    }
    
    /**
     * Submit player answer
     */
    public function submitAnswer(string $roomCode, string $sessionId, int $optionIndex, int $responseTimeMs): ?array
    {
        $game = $this->getGame($roomCode);
        
        if (!$game) {
            Log::error("QuizGame: submitAnswer - game not found for room: {$roomCode}");
            return null;
        }
        
        if ($game['status'] !== 'playing') {
            Log::error("QuizGame: submitAnswer - game status is '{$game['status']}', expected 'playing'");
            return null;
        }
        
        $playerIndex = $this->findPlayerIndex($game, $sessionId);
        
        if ($playerIndex === null) {
            Log::error("QuizGame: submitAnswer - player not found. Session ID: {$sessionId}, Players: " . json_encode(array_map(fn($p) => ['animal_name' => $p['animal_name'], 'session_id' => $p['session_id']], $game['players'])));
            return null;
        }
        
        Log::info("QuizGame: Player {$game['players'][$playerIndex]['animal_name']} (session: {$sessionId}) submitted answer {$optionIndex} in {$responseTimeMs}ms");
        
        $currentQuestion = $game['questions'][$game['current_question'] - 1] ?? null;
        
        if (!$currentQuestion) {
            return null;
        }
        
        // Check if already answered
        $answerKey = "{$sessionId}_{$game['current_question']}";
        if (isset($game['current_answers'][$answerKey])) {
            return $game; // Already answered
        }
        
        $isCorrect = ($optionIndex === $currentQuestion['correct_index']);
        
        // Calculate points: base 1000 points, bonus for speed
        $points = 0;
        if ($isCorrect) {
            $timeLimitMs = $game['question_time_limit'] * 1000;
            $speedBonus = max(0, ($timeLimitMs - $responseTimeMs) / $timeLimitMs);
            $points = (int)(500 + ($speedBonus * 500)); // 500-1000 points
        }
        
        // Save answer
        $game['current_answers'][$answerKey] = [
            'session_id' => $sessionId,
            'option_index' => $optionIndex,
            'is_correct' => $isCorrect,
            'response_time_ms' => $responseTimeMs,
            'points' => $points,
        ];
        
        // Update player score
        $game['players'][$playerIndex]['score'] += $points;
        
        $this->saveGame($roomCode, $game);
        
        // Check if all players have answered
        if ($this->haveAllPlayersAnswered($game)) {
            Log::info("QuizGame: All players answered - auto-closing question");
            $game = $this->nextQuestion($roomCode);
        }
        
        return $game;
    }
    
    /**
     * Check if all players have answered the current question
     */
    private function haveAllPlayersAnswered(array $game): bool
    {
        if ($game['status'] !== 'playing') {
            return false;
        }
        
        $currentQuestionNum = $game['current_question'];
        $totalPlayers = count($game['players']);
        $answeredCount = 0;
        
        foreach ($game['players'] as $player) {
            $answerKey = "{$player['session_id']}_{$currentQuestionNum}";
            if (isset($game['current_answers'][$answerKey])) {
                $answeredCount++;
            }
        }
        
        return $answeredCount === $totalPlayers && $totalPlayers > 0;
    }
    
    /**
     * Move to next question or finish game
     */
    public function nextQuestion(string $roomCode): ?array
    {
        $game = $this->getGame($roomCode);
        
        if (!$game || $game['status'] !== 'playing') {
            return null;
        }
        
        // Show results first
        if ($game['status'] === 'playing') {
            $game['status'] = 'results';
            $this->saveGame($roomCode, $game);
            return $game;
        }
        
        return null;
    }
    
    /**
     * Continue to next question after showing results
     */
    public function continueToNextQuestion(string $roomCode): ?array
    {
        $game = $this->getGame($roomCode);
        
        if (!$game || $game['status'] !== 'results') {
            return null;
        }
        
        // Check if there are more questions
        if ($game['current_question'] >= count($game['questions'])) {
            // Game finished
            $game['status'] = 'finished';
            $this->saveGame($roomCode, $game);
            return $game;
        }
        
        // Move to next question
        $game['current_question']++;
        $game['status'] = 'playing';
        $game['question_started_at'] = now()->toIso8601String();
        $game['current_answers'] = [];
        
        $this->saveGame($roomCode, $game);
        
        return $game;
    }
    
    /**
     * Get current question
     */
    public function getCurrentQuestion(string $roomCode): ?array
    {
        $game = $this->getGame($roomCode);
        
        if (!$game || empty($game['questions'])) {
            return null;
        }
        
        $questionIndex = $game['current_question'] - 1;
        
        return $game['questions'][$questionIndex] ?? null;
    }
    
    /**
     * Get leaderboard
     */
    public function getLeaderboard(string $roomCode): array
    {
        $game = $this->getGame($roomCode);
        
        if (!$game) {
            return [];
        }
        
        $players = $game['players'] ?? [];
        
        // Sort by score descending
        usort($players, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $players;
    }
    
    /**
     * Get answer statistics for current question
     */
    public function getAnswerStats(string $roomCode): array
    {
        $game = $this->getGame($roomCode);
        
        if (!$game) {
            return [];
        }
        
        $stats = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        
        foreach ($game['current_answers'] as $answer) {
            $optionIndex = $answer['option_index'];
            $stats[$optionIndex] = ($stats[$optionIndex] ?? 0) + 1;
        }
        
        return $stats;
    }
    
    /**
     * Remove player (disconnect)
     */
    public function removePlayer(string $roomCode, string $sessionId): void
    {
        $game = $this->getGame($roomCode);
        
        if (!$game) {
            return;
        }
        
        $playerIndex = $this->findPlayerIndex($game, $sessionId);
        
        if ($playerIndex !== null) {
            $game['players'][$playerIndex]['connected'] = false;
        }
        
        $this->saveGame($roomCode, $game);
    }
    
    /**
     * Delete game
     */
    public function deleteGame(string $roomCode): void
    {
        Cache::forget($this->gameKey($roomCode));
    }
    
    // Helper methods
    
    private function gameKey(string $roomCode): string
    {
        return self::CACHE_PREFIX . $roomCode;
    }
    
    private function findPlayerIndex(array $game, string $sessionId): ?int
    {
        foreach ($game['players'] as $index => $player) {
            if ($player['session_id'] === $sessionId) {
                return $index;
            }
        }
        
        return null;
    }
    
    private function saveGame(string $roomCode, array $game): void
    {
        Cache::put($this->gameKey($roomCode), $game, self::GAME_DURATION);
    }
}
