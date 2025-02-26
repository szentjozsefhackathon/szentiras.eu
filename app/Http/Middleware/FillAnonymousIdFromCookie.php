<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FillAnonymousIdFromCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('anonymous_token')) {
            return $next($request);
        } else if ($request->cookie('anonymous_token')) {
            $request->session()->put('anonymous_token', $request->cookie('anonymous_token'));
            return $next($request);
        } else {
            return $next($request);
        }
    }
}
