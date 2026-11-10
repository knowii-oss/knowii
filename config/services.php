<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // Plausible audience statistics through a proxy (#1316): off unless both values are set. The proxy serves the
    // script at /content/script.js and receives the events at /api/v1/event (a Cloudflare Worker for knowii.net)
    'plausible' => [
        'domain' => env('PLAUSIBLE_DOMAIN'),
        'proxy_url' => env('PLAUSIBLE_PROXY_URL'),
    ],

    // Waitlist on the welcome page (https://emailoctopus.com/api-documentation/v2)
    'emailoctopus' => [
        'base_url' => env('EMAILOCTOPUS_BASE_URL', 'https://api.emailoctopus.com'),
        'api_key' => env('EMAILOCTOPUS_API_KEY'),
        'waitlist_list_id' => env('EMAILOCTOPUS_WAITLIST_LIST_ID'),
        // 'pending': EmailOctopus waits for the confirmation of the address (double opt-in) before the contact is subscribed
        'waitlist_contact_status' => env('EMAILOCTOPUS_WAITLIST_CONTACT_STATUS', 'pending'),
        // Newsletter form of the footer (#938): hidden while no list ID is configured
        'newsletter_list_id' => env('EMAILOCTOPUS_NEWSLETTER_LIST_ID'),
        'newsletter_contact_status' => env('EMAILOCTOPUS_NEWSLETTER_CONTACT_STATUS', 'pending'),
    ],

    // Cloudflare Turnstile: bot check of the public forms (waitlist). https://developers.cloudflare.com/turnstile/
    // Without keys, the forms that need it refuse submissions.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
