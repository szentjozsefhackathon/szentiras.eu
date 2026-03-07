<?php

use Illuminate\Support\Facades\Route;
use SzentirasHu\Http\Controllers\Ai\AiController;
use SzentirasHu\Http\Controllers\Auth\AnonymousIdController;
use SzentirasHu\Http\Controllers\Display\GreekTextController;
use SzentirasHu\Http\Controllers\Display\TextDisplayController;
use SzentirasHu\Http\Controllers\Editor\AnonymousIdEditorController;
use SzentirasHu\Http\Controllers\Editor\ApiKeyController;
use SzentirasHu\Http\Controllers\Editor\ThemeController;
use SzentirasHu\Http\Controllers\Editor\CommentaryEditorController;
use SzentirasHu\Http\Controllers\Editor\ContactMessageEditorController;
use SzentirasHu\Http\Controllers\Contact\ContactController;
use SzentirasHu\Http\Controllers\Contact\InboxController;
use SzentirasHu\Http\Controllers\Home\HomeController;
use SzentirasHu\Http\Controllers\MediaController;
use SzentirasHu\Http\Controllers\Tools\ToolsController;
use SzentirasHu\Http\Controllers\Tools\MemoryGameController;
use SzentirasHu\Http\Controllers\Tools\OnlineMemoryGameController;
use SzentirasHu\Http\Controllers\Tools\GuessTheBookController;
use SzentirasHu\Http\Controllers\Tools\GuessTheMissingWordController;
use SzentirasHu\Http\Controllers\Tools\VerseScrambleController;
use SzentirasHu\Http\Controllers\Tools\WordFromNextVerseController;
use SzentirasHu\Http\Controllers\Tools\QuizGameController;
use SzentirasHu\Http\Middleware\RedirectLowerCaseTranslationAbbrev;
use SzentirasHu\Models\Media;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', [ HomeController::class, 'index' ]);

Route::get("/kereses", '\SzentirasHu\Http\Controllers\Search\SearchController@getIndex');
Route::post("/kereses/search", '\SzentirasHu\Http\Controllers\Search\SearchController@anySearch');
Route::post("/kereses/quicksearch", '\SzentirasHu\Http\Controllers\Search\SearchController@anySearch');
Route::get("/kereses/suggest", '\SzentirasHu\Http\Controllers\Search\SearchController@anySuggest');
Route::post("/kereses/suggest", '\SzentirasHu\Http\Controllers\Search\SearchController@anySuggest');
Route::post("/kereses/legacy", '\SzentirasHu\Http\Controllers\Search\SearchController@postLegacy');
Route::get("/kereses/suggestStrong", [ \SzentirasHu\Http\Controllers\Search\SearchController::class, 'suggestStrong']);
Route::get("/kereses/suggestGreek", [ \SzentirasHu\Http\Controllers\Search\SearchController::class, 'suggestGreek']);
Route::post("/kereses/greekSearch", '\SzentirasHu\Http\Controllers\Search\SearchController@greekSearch');


Route::get("/ai-search", '\SzentirasHu\Http\Controllers\Search\SemanticSearchController@getIndex');
Route::post("/ai-search/search", '\SzentirasHu\Http\Controllers\Search\SemanticSearchController@anySearch')
    ->middleware('throttle:100,1');

Route::get("/ai-tool/{translationAbbrev}/{refString}", [AiController::class, 'getAiToolPopover']);
Route::get("/ai-greek/find-all/{strongNumber}/{offset?}", [AiController::class, 'getAllInstancesOfGreekWord']);
Route::get("/ai-greek/{usx_code}/{chapter}/{verse}/{i}", [AiController::class, 'getGreekWordPanel']);

Route::post('/searchbible.php', '\SzentirasHu\Http\Controllers\Search\SearchController@postLegacy');

/** API */
Route::get("/api", '\SzentirasHu\Http\Controllers\Api\ApiController@getIndex')
    ->middleware('throttle:600,1');

Route::get('/info', '\SzentirasHu\Http\Controllers\Home\InfoController@getIndex');
Route::get('/impresszum', '\SzentirasHu\Http\Controllers\Home\InfoController@mission');
Route::get('/informaciok', '\SzentirasHu\Http\Controllers\Home\InfoController@informaciok');

Route::get('/pdf/dialog/{translationAbbrev}/{refString}', '\SzentirasHu\Http\Controllers\Display\PdfController@getDialog');
Route::get('/pdf/ref/{translationId}/{refString}', '\SzentirasHu\Http\Controllers\Display\PdfController@getRef');

Route::get('/verse-card/dialog/{translationAbbrev}/{refString}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@getDialog');
Route::post('/verse-card/find-themes', '\SzentirasHu\Http\Controllers\Display\VerseCardController@findThemes');
Route::post('/verse-card/create', '\SzentirasHu\Http\Controllers\Display\VerseCardController@createSession')->name('verse-card.create');
Route::get('/verse-card/creator/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@showCreator')->name('verse-card.creator');
Route::get('/verse-card/status/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@getStatus')->name('verse-card.status');
Route::post('/verse-card/more/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@requestMore')->name('verse-card.more');
Route::post('/verse-card/select/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@selectCandidate')->name('verse-card.select');
Route::post('/verse-card/update/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@updateAndRender')->name('verse-card.update');
Route::post('/verse-card/end/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@endSession')->name('verse-card.end');
Route::get('/verse-card/download/{sessionId}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@download')->name('verse-card.download');
Route::get('/verse-card/asset/{assetId}/{type}', '\SzentirasHu\Http\Controllers\Display\VerseCardController@serveAsset')->name('verse-card.asset');

/** AUDIO */
Route::get('/hang', '\SzentirasHu\Http\Controllers\Display\AudioBookController@index');

Route::get('/hang/{id}', '\SzentirasHu\Http\Controllers\Display\AudioBookController@show')
    ->where('id', '.+');

/** Places */
Route::get('/place/{placeIds}', [TextDisplayController::class, 'showPlaceDetails'])->where('placeIds', '[0-9,]+');

/** QR code */
Route::get('/qr/dialog/{url}', '\SzentirasHu\Http\Controllers\Display\\QrCodeController@dialog')->where('url', '.+');
Route::get('/qr/img/{url}', '\SzentirasHu\Http\Controllers\Display\\QrCodeController@index')->where('url', '.+');

Route::get('/forditasok', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showTranslationList');

Route::get('/tervek/{plan_id}/{day_number}', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showReadingPlanDay')
    ->where(['plan_id' => '.+', 'day_number' => '.+']);

Route::get('/tervek/{id}', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showReadingPlan')
    ->where('id', '.+');

Route::get('/tervek', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showReadingPlanList');

Route::get('/register', [AnonymousIdController::class, 'showAnonymousRegistrationForm']);
Route::post('/register', [AnonymousIdController::class, 'registerAnonymousId']);
Route::get('/profile/{PROFILE_ID}', [AnonymousIdController::class, 'showProfile'])
    ->middleware('throttle:100,1');
Route::get('/profile', [AnonymousIdController::class, 'showProfile'])
    ->middleware('anonymousId');
Route::get('/logout', [AnonymousIdController::class, 'logout'])
    ->middleware('anonymousId');
Route::post('/login', [AnonymousIdController::class, 'login']);
Route::get('/login', [AnonymousIdController::class, 'showLoginForm']);

// Contact routes
Route::get('/contact', [ContactController::class, 'showForm'])->name('contact.form');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
Route::get('/contact/thankyou', [ContactController::class, 'thankYou'])->name('contact.thankyou');

// Tools routes
Route::get('/tools', [ToolsController::class, 'index'])->name('tools.index');
Route::get('/tools/memorygame', [MemoryGameController::class, 'index'])->name('tools.memoryGame');
Route::post('/tools/memorygame', [MemoryGameController::class, 'index'])->name('tools.memoryGame.process');
Route::match(['get', 'post'], '/tools/guessbook', [GuessTheBookController::class, 'index']);
Route::match(['get', 'post'], '/tools/memory-game-play', [OnlineMemoryGameController::class, 'index']);
Route::match(['get', 'post'], '/tools/guess-word', [GuessTheMissingWordController::class, 'index']);
Route::match(['get', 'post'], '/tools/verse-scramble', [VerseScrambleController::class, 'index']);
Route::match(['get', 'post'], '/tools/word-from-next-verse', [WordFromNextVerseController::class, 'index']);

// Quiz game routes
Route::get('/quiz', [QuizGameController::class, 'index'])->name('quiz.index');
Route::post('/quiz/create', [QuizGameController::class, 'createGame'])->name('quiz.create');
Route::get('/quiz/teacher/{roomCode}', [QuizGameController::class, 'teacherView'])->name('quiz.teacher');
Route::get('/quiz/player/{roomCode}', [QuizGameController::class, 'playerView'])->name('quiz.player');
Route::get('/quiz/animals/{roomCode}', [QuizGameController::class, 'getAvailableAnimals'])->name('quiz.animals');
Route::post('/quiz/join/{roomCode}', [QuizGameController::class, 'joinGame'])->name('quiz.join');
Route::post('/quiz/start/{roomCode}', [QuizGameController::class, 'startGame'])->name('quiz.start');
Route::post('/quiz/answer/{roomCode}', [QuizGameController::class, 'submitAnswer'])->name('quiz.answer');
Route::post('/quiz/results/{roomCode}', [QuizGameController::class, 'showResults'])->name('quiz.results');
Route::post('/quiz/next/{roomCode}', [QuizGameController::class, 'nextQuestion'])->name('quiz.next');
Route::get('/quiz/state/{roomCode}', [QuizGameController::class, 'getGameState'])->name('quiz.state');
Route::post('/quiz/end/{roomCode}', [QuizGameController::class, 'endGame'])->name('quiz.end');

// User inbox routes (require anonymous login)
Route::middleware('anonymousId')->group(function () {
    Route::get('/inbox', [InboxController::class, 'index'])->name('contact.inbox');
    Route::get('/inbox/{message}', [InboxController::class, 'showThread'])->name('contact.thread');
    Route::post('/inbox/{message}/reply', [InboxController::class, 'reply'])->name('contact.reply');
});

Route::get('/media/{uuid}', [MediaController::class, 'show'])->name('media.show');

// Editor routes
Route::middleware('editor')->group(function () {
    // Commentary editor (except generate which has special permissions)
    Route::prefix('editor/commentaries')->name('editor.commentaries.')->group(function () {
        Route::get('/', [CommentaryEditorController::class, 'index'])->name('index');
        Route::get('/{commentary}', [CommentaryEditorController::class, 'show'])->name('show');
        Route::get('/{commentary}/status', [CommentaryEditorController::class, 'status'])->name('status');
        Route::put('/{commentary}', [CommentaryEditorController::class, 'update'])->name('update');
        Route::delete('/{commentary}', [CommentaryEditorController::class, 'destroy'])->name('destroy');
    });

    // Anonymous IDs editor
    Route::prefix('editor/anonymous-ids')->name('editor.anonymousIds.')->group(function () {
        Route::get('/', [AnonymousIdEditorController::class, 'index'])->name('index');
    });

    // Contact messages editor
    Route::prefix('editor/contact-messages')->name('editor.contactMessages.')->group(function () {
        Route::get('/', [ContactMessageEditorController::class, 'index'])->name('index');
        Route::get('/{message}', [ContactMessageEditorController::class, 'showThread'])->name('thread');
        Route::post('/{message}/reply', [ContactMessageEditorController::class, 'reply'])->name('reply');
        Route::post('/{message}/resolve', [ContactMessageEditorController::class, 'markResolved'])->name('resolve');
        Route::post('/{message}/delete', [ContactMessageEditorController::class, 'delete'])->name('delete');
    });

    // API keys editor
    Route::prefix('editor/api-keys')->name('editor.apiKeys.')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index'])->name('index');
        Route::get('/create', [ApiKeyController::class, 'create'])->name('create');
        Route::post('/', [ApiKeyController::class, 'store'])->name('store');
        Route::get('/{apiKey}', [ApiKeyController::class, 'show'])->name('show');
        Route::get('/{apiKey}/edit', [ApiKeyController::class, 'edit'])->name('edit');
        Route::put('/{apiKey}', [ApiKeyController::class, 'update'])->name('update');
        Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy'])->name('destroy');
    });

    // Themes editor
    Route::prefix('editor/themes')->name('editor.themes.')->group(function () {
        Route::get('/', [ThemeController::class, 'index'])->name('index');
        Route::get('/create', [ThemeController::class, 'create'])->name('create');
        Route::post('/', [ThemeController::class, 'store'])->name('store');
        Route::post('/test-similarity', [ThemeController::class, 'testSimilarity'])->name('testSimilarity');
        Route::get('/{theme}', [ThemeController::class, 'show'])->name('show');
        Route::get('/{theme}', [ThemeController::class, 'show'])->name('show');
        Route::get('/{theme}/edit', [ThemeController::class, 'edit'])->name('edit');
        Route::put('/{theme}', [ThemeController::class, 'update'])->name('update');
        Route::delete('/{theme}', [ThemeController::class, 'destroy'])->name('destroy');
    });
});

// Commentary generation route - allows editors OR logged-in users when config permits
Route::middleware('commentaryGeneration')->group(function () {
    Route::post('/editor/commentaries/generate', [CommentaryEditorController::class, 'generate'])->name('editor.commentaries.generate');
});

// Public API for commentary status
Route::get('/api/commentaries/status', [CommentaryEditorController::class, 'statusByReference']);

// Internal API for books (used by frontend components)
Route::get('/internal-api/books/{translationAbbrev?}', [\SzentirasHu\Http\Controllers\Api\ApiController::class, 'getBooks'])
    ->middleware('same-origin');

// Media API endpoints for editors
Route::prefix('api/media')->middleware('editor')->group(function () {
    Route::get('/{id}', [\SzentirasHu\Http\Controllers\Api\MediaApiController::class, 'show']);
    Route::post('/move', [\SzentirasHu\Http\Controllers\Api\MediaApiController::class, 'move']);
    Route::delete('/{id}', [\SzentirasHu\Http\Controllers\Api\MediaApiController::class, 'delete']);
    Route::get('/{usxCode}/{chapter}/{verse}/next', [\SzentirasHu\Http\Controllers\Api\MediaApiController::class, 'getNextVerse']);
    Route::get('/{usxCode}/{chapter}/{verse}/previous', [\SzentirasHu\Http\Controllers\Api\MediaApiController::class, 'getPreviousVerse']);
});

Route::get('/GNT/{reference?}', [GreekTextController::class, 'show'])->where('reference', '[^/]+');

/** These should come at the end to not collide with other routes! */
Route::get('/{TRANSLATION_ABBREV}', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showTranslation')
    ->middleware(RedirectLowerCaseTranslationAbbrev::class)
    ->where('TRANSLATION_ABBREV', Config::get('settings.translationAbbrevRegex'));

Route::get('/{TRANSLATION_ABBREV}/{REFERENCE}', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showTranslatedReferenceText')
    ->middleware(RedirectLowerCaseTranslationAbbrev::class)
    ->name('textDisplay.show')
    ->where(['TRANSLATION_ABBREV' => Config::get('settings.translationAbbrevRegex'),
        'REFERENCE' => '[^/]+']);

Route::get('/{REFERENCE}', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showReferenceText')
     ->where('REFERENCE', '[^/]+');
Route::get('/xref/{TRANSLATION_ABBREV}/{REFERENCE}', [TextDisplayController::class, 'showXrefText'])
    ->middleware(RedirectLowerCaseTranslationAbbrev::class)
    ->where(['TRANSLATION_ABBREV' => Config::get('settings.translationAbbrevRegex'),
        'REFERENCE' => '[^/]+']);
