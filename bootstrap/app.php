<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use SzentirasHu\Http\Middleware\CacheAnonymousResponse;
use SzentirasHu\Http\Middleware\CheckCommentaryGeneration;
use SzentirasHu\Http\Middleware\CheckEditor;
use SzentirasHu\Http\Middleware\FillAnonymousIdFromCookie;
use SzentirasHu\Http\Middleware\MarkCacheable;
use SzentirasHu\Http\Middleware\NoIndex;
use SzentirasHu\Http\Middleware\SameOrigin;
use SzentirasHu\Http\Middleware\ValidateAnonymousId;
use SzentirasHu\Http\Middleware\VerifyApiKey;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12'
        ]);    
        $middleware->alias([
            'anonymousId' => ValidateAnonymousId::class,
            'editor' => CheckEditor::class,
            'commentaryGeneration' => CheckCommentaryGeneration::class,
            'apiKey' => VerifyApiKey::class,
            'same-origin' => SameOrigin::class,
            'cacheable' => MarkCacheable::class,
            'noindex' => NoIndex::class,
        ]);
        $middleware->web(prepend: [CacheAnonymousResponse::class]);
        $middleware->web(append: [FillAnonymousIdFromCookie::class]);

        // Search is a read-only operation; exempting it from CSRF lets its form
        // live on cookie-free, CDN-cached pages without a session-bound token.
        $middleware->validateCsrfTokens(except: [
            'kereses/search',
            'kereses/quicksearch',
            'kereses/suggest',
            'kereses/legacy',
            'kereses/greekSearch',
            'searchbible.php',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('szentiras:fetch-daily-reading')
            ->dailyAt('04:00')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();