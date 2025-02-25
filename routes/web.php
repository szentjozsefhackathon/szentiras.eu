<?php

use Illuminate\Support\Facades\Route;
use SzentirasHu\Http\Controllers\Ai\AiController;
use SzentirasHu\Http\Controllers\Auth\AnonymousIdController;
use SzentirasHu\Http\Controllers\Display\TextDisplayController;
use SzentirasHu\Http\Controllers\Home\HomeController;
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

Route::get('/', [ HomeController::class, 'index' ]);

Route::get("/kereses", '\SzentirasHu\Http\Controllers\Search\SearchController@getIndex');
Route::post("/kereses/search", '\SzentirasHu\Http\Controllers\Search\SearchController@anySearch');
Route::get("/kereses/suggest", '\SzentirasHu\Http\Controllers\Search\SearchController@anySuggest');
Route::post("/kereses/suggest", '\SzentirasHu\Http\Controllers\Search\SearchController@anySuggest');
Route::post("/kereses/legacy", '\SzentirasHu\Http\Controllers\Search\SearchController@postLegacy');

Route::get("/ai-search", '\SzentirasHu\Http\Controllers\Search\SemanticSearchController@getIndex');
Route::post("/ai-search/search", '\SzentirasHu\Http\Controllers\Search\SemanticSearchController@anySearch')
    ->middleware('throttle:10,1');

Route::get("/ai-tool/{translationAbbrev}/{refString}", [AiController::class, 'getAiToolPopover']);

Route::post('/searchbible.php', ' \SzentirasHu\Http\Controllers\Search\SearchController@postLegacy');

/** API */
Route::get("/api", '\SzentirasHu\Http\Controllers\Api\ApiController@getIndex');

Route::get('/info', '\SzentirasHu\Http\Controllers\Home\InfoController@getIndex');

Route::get('/pdf/dialog/{translationAbbrev}/{refString}', '\SzentirasHu\Http\Controllers\Display\PdfController@getDialog');
Route::get('/pdf/ref/{translationId}/{refString}', '\SzentirasHu\Http\Controllers\Display\PdfController@getRef');

/** AUDIO */
Route::get('/hang', '\SzentirasHu\Http\Controllers\Display\AudioBookController@index');

Route::get('/hang/{id}', '\SzentirasHu\Http\Controllers\Display\AudioBookController@show')
    ->where('id', '.+');

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
    ->middleware('throttle:10,1');
Route::get('/profile', [AnonymousIdController::class, 'showProfile'])
    ->middleware('anonymousId');
Route::get('/logout', [AnonymousIdController::class, 'logout'])
    ->middleware('anonymousId');
Route::post('/login', [AnonymousIdController::class, 'login']);

Route::get('/media/{uuid}', [MediaController::class, 'show'])->name('media.show');

/** These should come at the end to not collide with other routes! */
Route::get('/{TRANSLATION_ABBREV}', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showTranslation')
    ->where('TRANSLATION_ABBREV', Config::get('settings.translationAbbrevRegex'));

Route::get('/{TRANSLATION_ABBREV}/{REFERENCE}', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showTranslatedReferenceText')
    ->where(['TRANSLATION_ABBREV' => Config::get('settings.translationAbbrevRegex'),
        'REFERENCE' => '[^/]+']);

Route::get('/{REFERENCE}', '\SzentirasHu\Http\Controllers\Display\\TextDisplayController@showReferenceText')
     ->where('REFERENCE', '[^/]+');
Route::get('/xref/{TRANSLATION_ABBREV}/{REFERENCE}', [TextDisplayController::class, 'showXrefText'])
    ->where(['TRANSLATION_ABBREV' => Config::get('settings.translationAbbrevRegex'),
        'REFERENCE' => '[^/]+']);