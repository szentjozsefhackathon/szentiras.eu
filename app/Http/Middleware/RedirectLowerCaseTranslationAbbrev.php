<?php

namespace SzentirasHu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class RedirectLowerCaseTranslationAbbrev
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $translationAbbrev = $request->route('TRANSLATION_ABBREV');
        // Check if the path is not in uppercase
        if ($translationAbbrev && $translationAbbrev !== strtoupper($translationAbbrev)) {
            // Redirect to the uppercase version with a 301 status code
            $uppercasePath = Str::replaceFirst($translationAbbrev, strtoupper($translationAbbrev), $request->getPathInfo());
            return redirect($uppercasePath, 301);
        }

        // Proceed with the request
        return $next($request);
    }
}
