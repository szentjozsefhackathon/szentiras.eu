<?php

use Illuminate\Support\Facades\Route;
use SzentirasHu\Http\Controllers\Ai\AiController;
use SzentirasHu\Http\Controllers\Auth\AnonymousIdController;
use SzentirasHu\Http\Controllers\Display\TextDisplayController;
use SzentirasHu\Http\Controllers\MediaController;
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

Route::get('/', 'Home\HomeController@index');

Route::get("/kereses", 'Search\SearchController@getIndex');
Route::post("/kereses/search", 'Search\SearchController@anySearch');
Route::get("/kereses/suggest", 'Search\SearchController@anySuggest');
Route::post("/kereses/suggest", 'Search\SearchController@anySuggest');
Route::post("/kereses/legacy", 'Search\SearchController@postLegacy');

Route::get("/ai-search", 'Search\SemanticSearchController@getIndex');
Route::post("/ai-search/search", 'Search\SemanticSearchController@anySearch')
    ->middleware('throttle:10,1');

Route::get("/ai-tool/{translationAbbrev}/{refString}", [AiController::class, 'getAiToolPopover']);

Route::post('/searchbible.php', 'SzentirasHu\Http\Controllers\Search\SearchController@postLegacy');

/** API */
Route::get("/api", 'Api\ApiController@getIndex');

Route::get('/info', 'Home\InfoController@getIndex');

Route::get('/pdf/dialog/{translationAbbrev}/{refString}', 'Display\PdfController@getDialog');
Route::get('/pdf/ref/{translationId}/{refString}', 'Display\PdfController@getRef');

/** AUDIO */
Route::get('/hang', 'Display\AudioBookController@index');

Route::get('/hang/{id}', 'Display\AudioBookController@show')
    ->where('id', '.+');

/** QR code */
Route::get('/qr/dialog/{url}', 'Display\\QrCodeController@dialog')->where('url', '.+');
Route::get('/qr/img/{url}', 'Display\\QrCodeController@index')->where('url', '.+');

Route::get('/forditasok', 'Display\\TextDisplayController@showTranslationList');

Route::get('/tervek/{plan_id}/{day_number}', 'Display\\TextDisplayController@showReadingPlanDay')
    ->where(['plan_id' => '.+', 'day_number' => '.+']);

Route::get('/tervek/{id}', 'Display\\TextDisplayController@showReadingPlan')
    ->where('id', '.+');

Route::get('/tervek', 'Display\\TextDisplayController@showReadingPlanList');

Route::get('/register', [AnonymousIdController::class, 'showAnonymousRegistrationForm']);
Route::post('/register', [AnonymousIdController::class, 'registerAnonymousId']);
Route::get('/profile/{PROFILE_ID}', [AnonymousIdController::class, 'showProfile'])
    ->middleware('throttle:10,1');
Route::get('/profile', [AnonymousIdController::class, 'showProfile'])
    ->middleware('anonymousId');
Route::get('/logout', [AnonymousIdController::class, 'logout'])
    ->middleware('anonymousId');
Route::post('/login', [AnonymousIdController::class, 'login']);

Route::get('/media/{uuid}', [MediaController::class, 'show'])->name('media.show');

/** These should come at the end to not collide with other routes! */
Route::get('/{TRANSLATION_ABBREV}', 'Display\\TextDisplayController@showTranslation')
    ->where('TRANSLATION_ABBREV', Config::get('settings.translationAbbrevRegex'));

Route::get('/{TRANSLATION_ABBREV}/{REFERENCE}', 'Display\\TextDisplayController@showTranslatedReferenceText')
    ->where(['TRANSLATION_ABBREV' => Config::get('settings.translationAbbrevRegex'),
        'REFERENCE' => '[^/]+']);

Route::get('/{REFERENCE}', 'Display\\TextDisplayController@showReferenceText')
     ->where('REFERENCE', '[^/]+');
Route::get('/xref/{TRANSLATION_ABBREV}/{REFERENCE}', [TextDisplayController::class, 'showXrefText'])
    ->where(['TRANSLATION_ABBREV' => Config::get('settings.translationAbbrevRegex'),
        'REFERENCE' => '[^/]+']);