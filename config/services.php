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
    // script at /content/script.js and receives the events at /api/v1/event (for example https://stats.example.com)
    'plausible' => [
        'domain' => env('PLAUSIBLE_DOMAIN'),
        'proxy_url' => env('PLAUSIBLE_PROXY_URL'),
    ],

    // The mailing lists (https://emailoctopus.com/api-documentation/v2)
    'emailoctopus' => [
        'base_url' => env('EMAILOCTOPUS_BASE_URL', 'https://api.emailoctopus.com'),
        'api_key' => env('EMAILOCTOPUS_API_KEY'),
        // The list of the waitlist: it takes no new address (#1574). Only the prune of its old contacts reads it (ADR 0030)
        'waitlist_list_id' => env('EMAILOCTOPUS_WAITLIST_LIST_ID'),
        // Newsletter form of the footer (#938): hidden while no list ID is configured. 'pending': EmailOctopus waits for the
        // confirmation of the address (double opt-in) before the contact is subscribed
        'newsletter_list_id' => env('EMAILOCTOPUS_NEWSLETTER_LIST_ID'),
        'newsletter_contact_status' => env('EMAILOCTOPUS_NEWSLETTER_CONTACT_STATUS', 'pending'),
    ],

    // Cloudflare Turnstile: bot check of the public forms (sign-up, newsletter). https://developers.cloudflare.com/turnstile/
    // Without keys, the forms that need it refuse submissions.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],

    // Sign in with an OpenID Connect provider (#1385, ADR 0045). Off unless the issuer, the client ID and the client
    // secret are all set. The scopes never include offline_access: no refresh token is asked or stored
    'oidc' => [
        'issuer' => env('OIDC_ISSUER'),
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'scopes' => env('OIDC_SCOPES', 'openid profile email'),
        // The text of the button. Without it, the host name of the issuer
        'label' => env('OIDC_LABEL'),
        // An account without a password confirms its deletion with a new sign-in at the provider (#1521, ADR 0052): the sign-in
        // must be at most this many seconds old, and the proof holds this long after it. Kept between 60 and 900
        'fresh_sign_in_seconds' => (int) env('OIDC_FRESH_SIGN_IN_SECONDS', 300),
        // Entitlements of the provider feed the access groups (#1386, ADR 0047). Off unless the address template is set. The
        // communities that may use rules are listed by slug: empty means none
        'entitlements_url' => env('OIDC_ENTITLEMENTS_URL'),
        'entitlements_scope' => env('OIDC_ENTITLEMENTS_SCOPE', 'entitlements:read:subjects'),
        'entitlements_resource' => env('OIDC_ENTITLEMENTS_RESOURCE'),
        'entitlements_sign_in_scope' => env('OIDC_ENTITLEMENTS_SIGN_IN_SCOPE', 'entitlements:read'),
        'entitlement_communities' => env('OIDC_ENTITLEMENT_COMMUNITIES'),
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
