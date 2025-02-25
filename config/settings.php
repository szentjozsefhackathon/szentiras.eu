<?php

return [
    'defaultTranslationId' => env("DEFAULT_TRANSLATION_ID", 3),
    'defaultTranslationAbbrev' => env("DEFAULT_TRANSLATION_ABBREV", "KNB"),
    'translationAbbrevRegex' => env("TRANSLATION_ABBREV_REGEX", "KNB|SZIT|UF|KG|BD|RUF|STL|knb|szit|uf|kg|bd|ruf|stl"),
    'enabledTranslations' => preg_split("/, ?/", env("ENABLED_TRANSLATIONS", "1,3,4,5,6,7")),
    'audioDirectory' => env("AUDIO_DIRECTORY", 'hang'),
    'sourceDirectory' => '/tmp',
    'facebookAppId' => '679257202109581',
    'sphinxSearchLimit' => env("APP_SPHINX_SEARCH_LIMIT", 1000),
    'logLevel' => env("LOG_LEVEL", 'debug'),
    'sphinxIndexerTrigger' => env("APP_SPHINX_INDEXER_TRIGGER", "/opt/sphinx/trigger/indexer"),
    'sphinxHost' => env('SPHINX_HOST', 'sphinx'),
    'sphinxPort' => env('SPHINX_PORT', 9312),
    'sphinxIndexes' => [
        'name' => ['verse', 'verse_root'],
        'mapping' => false
    ],
    'googleAppName' => 'szentiras-hu',
    'googleApiKey' => env('GOOGLE_API_KEY'),
    'googleCalendarId' => 'katolikus.hu@gmail.com',
    'ai' => [
        'embeddingModel' => env("APP_EMBEDDING_MODEL", 'text-embedding-3-large'),
        'embeddingDimensions' => env("APP_EMBEDDING_DIMENSIONS", 2000),
        'unregisteredSearchLimit' => env("APP_UNREGISTERED_SEARCH_LIMIT", 5),
    ],
    'brand' => [
        'domain' => env("APP_BRAND_DOMAIN", 'szentiras.eu')
    ]
];
