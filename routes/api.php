<?php

Route::get("idezet/{refString}/{translationAbbrev?}", 'Api\ApiController@getIdezet');
Route::get("forditasok", 'Api\ApiController@getTranslationList');
Route::get("forditasok/{gepi}", 'Api\ApiController@getForditasok');
Route::get("lectures", 'Api\ApiController@getLectures');
Route::get("books/{translationAbbrev?}", 'Api\ApiController@getBooks');
Route::get("ref/{ref}/{translationAbbrev?}", 'Api\ApiController@getRef');
Route::get("search/{text}", 'Api\ApiController@getSearch');

Route::get('/API', 'Api\ApiController@getLegacyEndpoint');

// Route::get('/cosine', 'Api\ApiController@getCosineSimilarity');