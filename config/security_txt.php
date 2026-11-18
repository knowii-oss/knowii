<?php

/*
 * security.txt of the instance (#1559, RFC 9116), served at /.well-known/security.txt. Each instance names its own
 * contact: the file is built from these values, and the repository holds no address of one instance.
 */
return [
    // Where a person reports a security problem: mailto:, https: or tel: addresses, separated by commas, in the order of
    // preference. Empty: the instance has no security.txt (both addresses answer 404).
    'contacts' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_TXT_CONTACTS', ''))))),

    // The date and time after which the file is stale (ISO 8601, for example 2027-06-30T00:00:00Z). Empty: 180 days after
    // the current day. The file is built from the configuration that runs, so a date that moves stays true.
    'expires' => env('SECURITY_TXT_EXPIRES'),

    // The page with the security policy of this instance (https). Empty: the security page of the application.
    'policy' => env('SECURITY_TXT_POLICY'),

    // Optional (https): the public key for encrypted reports, and the page that thanks the reporters.
    'encryption' => env('SECURITY_TXT_ENCRYPTION'),
    'acknowledgments' => env('SECURITY_TXT_ACKNOWLEDGMENTS'),

    // The languages of a report that the team reads (language tags, separated by commas).
    'preferred_languages' => env('SECURITY_TXT_PREFERRED_LANGUAGES', 'en'),
];
