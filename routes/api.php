<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['apiKey', 'throttle:api_key'])->group(function () {
    Route::get("idezet/{refString}/{translationAbbrev?}", 'Api\ApiController@getIdezet');
    Route::get("forditasok", 'Api\ApiController@getTranslationList');
    Route::get("forditasok/{gepi}", 'Api\ApiController@getForditasok');
    Route::get("books/{translationAbbrev?}", 'Api\ApiController@getBooks');
    Route::get("ref/{ref}/{translationAbbrev?}", 'Api\ApiController@getRef');
    Route::get("search/{text}/{translationAbbrev?}", 'Api\ApiController@getSearch');
    Route::get("greek-search/{text}/{translationAbbrev?}", 'Api\ApiController@getGreekSearch');
    Route::get('/API', 'Api\ApiController@getLegacyEndpoint');
    // Route::get('/cosine', 'Api\ApiController@getCosineSimilarity');
});