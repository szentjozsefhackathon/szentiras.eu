<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, Mandrill, and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'mandrill' => [
        'secret' => env('MANDRILL_SECRET'),
    ],

    'ses' => [
        'key'    => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

'cloudflare_turnstile' => [
        'site_key' => env('CLOUDFLARE_TURNSTILE_SITE_KEY', '1x00000000000000000000AA'),
        'secret_key' => env('CLOUDFLARE_TURNSTILE_SECRET_KEY'),
        'url' => env('CLOUDFLARE_TURNSTILE_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify')
    ],

'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY')
]


];
