<?php

namespace SzentirasHu\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class TurnstileValidationRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // allow dummy token
        if (false === strpos($value, 'XXXX') && !$this->validateToken($value)) {
            $fail("Sikertelen captcha validáció!");
        }
    }

    private function validateToken(string $token): bool
    {
        $response = $this->getResponse($token);
        return $response->json('success') === true;
    }

    private function getResponse(string $token)
    {
        $response = Http::retry(4, 100)
            ->acceptJson()
            ->asForm()
            ->post(url: Config::get("services.cloudflare_turnstile.url"), data: [
                'response' => $token,
                'secret'   => Config::get("services.cloudflare_turnstile.secret_key"),
            ]);

        return $response;
    }

}
