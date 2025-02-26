<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use SzentirasHu\Http\Middleware\FillAnonymousIdFromCookie;
use SzentirasHu\Http\Middleware\ValidateAnonymousId;

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
        $middleware->alias(['anonymousId' => ValidateAnonymousId::class]);
        $middleware->web(append: [FillAnonymousIdFromCookie::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();