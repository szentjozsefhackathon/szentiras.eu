<?php

namespace SzentirasHu\Http\Controllers\Auth;

use Illuminate\Support\Facades\Cookie;
use Redirect;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Http\Controllers\Controller;

class AnonymousIdController extends Controller
{
    
    public function showAnonymousRegistrationForm() {
        if (session()->has('anonymous_token')) {
            return Redirect::to('/profile');
        }
        return view("auth.anonymousRegistration");
    }

    public function registerAnonymousId() {
        // Validate user approval
        request()->validate([
            'approve' => 'accepted',
        ]);
        $token = $this->generateToken();
        // Save token to database
        $anonymousId = AnonymousId::create([
            'token' => $token,
            'last_login' => now(),
        ]);        
        return Redirect::to("/profile/{$anonymousId->token}");
    }

    public function login() {
        $token = request('anonymous_token');
        $anonymousId = AnonymousId::where(
            'token', $token
        )->first();
        request()->validate([
            'anonymous_token' => 'required|exists:anonymous_ids,token',
        ]);
        session(['anonymous_token' => $anonymousId->token]);
        Cookie::queue(Cookie::forever('anonymous_token', $anonymousId->token));
        return Redirect::to('/');
    }

    public function showProfile(string $profileId = null) {
        if (is_null($profileId)) {
            $profileId= session('anonymous_token');
        }
        $anonymousId = AnonymousId::where(
            'token', $profileId
        )->first();
        if (empty($anonymousId)) {
            abort(403, 'Érvénytelen névtelen azonosító');
        }
        session(['anonymous_token' => $anonymousId->token]);
        Cookie::queue(Cookie::forever('anonymous_token', $anonymousId->token));
        return view("auth.anonymousId", ['anonymousId' => $anonymousId]);
    }
    
    public function logout() {
        Cookie::queue(Cookie::forget('anonymous_token'));
        session()->forget('anonymous_token');
        return Redirect::to('/');   
    }

    private function generateToken() {
        return $this->shortenUuid((string)\Illuminate\Support\Str::uuid()->getHex());
    }

    private function shortenUuid(string $hexUuid)  {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $value = new \GMP($hexUuid, 16);
        $result = '';

        while (gmp_cmp($value, 0) > 0) {
            list($value, $remainder) = gmp_div_qr($value, 62);
            $result .= $chars[gmp_intval($remainder)];
        }
        return strrev($result);
    }
    
}
