<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;
use SzentirasHu\Data\Entity\AnonymousId;

class FillAnonymousIdFromCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = null;
        if ($request->session()->has('anonymous_token')) {
            $token = $request->session()->get('anonymous_token');
        } else if ($request->cookie('anonymous_token')) {
            $token = $request->cookie('anonymous_token');
        }
        if ($token) {
            $anonymousId = AnonymousId::where(
                'token', $token
            )->first();
            if ($anonymousId) {
                $request->session()->put('anonymous_token', $token);
                Cookie::queue(Cookie::forever('anonymous_token', $token));
            } else {
                $request->session()->forget('anonymous_token');
                Cookie::queue(Cookie::forget('anonymous_token'));
            }
        }
        return $next($request);
    }
}
