<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Cookie;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use SzentirasHu\Data\Entity\AnonymousId;

class ValidateAnonymousId
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
            // lookup token in database
            $token = $request->session()->get('anonymous_token');
        } else if ($request->cookie('anonymous_token')) {
            $token = $request->cookie('anonymous_token');
        }
        if ($token) {
            $anonymousId = AnonymousId::where(
                'token', $token
            )->first();
            if ($anonymousId) {
                return $next($request);
            } else {
                $request->session()->forget('anonymous_token');
                Cookie::queue(Cookie::forget('anonymous_token'));
            }
        }
        return redirect('/register');
    }
}
