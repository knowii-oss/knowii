<?php

use App\Constants;

/*
|--------------------------------------------------------------------------
| Legal facts of the instance: the operator, its contact address and the sub-processors (#904, #1653)
|--------------------------------------------------------------------------
|
| The privacy policy and the data processing agreement show the sub-processors through the {{subprocessors}}
| placeholder. A pull request that adds a third-party service that receives personal data (an AI provider, an email
| or analytics service, a storage) adds it here (rule 9 of the data rules in documentation/conventions/rules.md).
| The DPA promises 14 days of notice to the community owners before a new sub-processor applies. A sub-processor that only an
| optional feature uses (an AI feature that each owner turns on) sets 'feature': it needs no notice, and the table says so.
|
| 'scope' says whose data a sub-processor receives (#1352): 'community' for the data of the communities (the operator
| is the processor: the DPA lists it), 'platform' for the data of the accounts, the waitlist, the newsletter and the
| statistics (the operator is the controller: only the privacy policy lists it). Without a scope, both pages list it.
|
*/

// A required value of the instance (#1653, ADR 0055). No default names an operator or an address. A value that is empty or
// blank is not set (KEY= in .env gives an empty text, not a default). Outside production a value that is not set gets a
// neutral placeholder. In production it stays empty, and the instance refuses to start (App\Support\InstanceValues)
$required = static function (string $name, string $placeholder): string {
    $value = env($name);
    $value = is_string($value) ? trim($value) : '';
    if ($value !== '') {
        return $value;
    }

    return env('APP_ENV', 'production') !== 'production' ? $placeholder : '';
};

return [
    /*
    | Who runs this instance: the invitation email names it (GDPR art. 14, #1345). Required in production.
    */
    'operator' => $required('LEGAL_OPERATOR', Constants::INSTANCE_PLACEHOLDER_OPERATOR),

    /*
    | The public contact address of the operator: the contact page, the footer and the account deletion texts show it.
    | Required in production.
    */
    'contact_email' => $required('LEGAL_CONTACT_EMAIL', Constants::INSTANCE_PLACEHOLDER_CONTACT_EMAIL),

    'subprocessors' => [
        [
            'name' => 'Hetzner Online GmbH',
            'country' => 'Germany',
            'purpose' => 'Hosting of the servers: application, database, uploaded files',
            'safeguard' => 'European Union',
            'scope' => ['platform', 'community'],
        ],
        [
            'name' => 'Cloudflare, Inc.',
            'country' => 'United States',
            'purpose' => 'DNS, proxy and TLS for all traffic (IP addresses), Turnstile bot checks, storage of the encrypted backups',
            'safeguard' => 'EU-US Data Privacy Framework',
            'scope' => ['platform', 'community'],
        ],
        [
            'name' => 'Resend, Inc.',
            'country' => 'United States',
            'purpose' => 'Emails of the service: sign-in links, address verification, invitations',
            // Certified under the Data Privacy Framework since 2025; its data processing addendum keeps the standard
            // contractual clauses as the fallback, like the one of Cloudflare (#1347)
            'safeguard' => 'EU-US Data Privacy Framework',
            'scope' => ['platform', 'community'],
        ],
        [
            'name' => 'EmailOctopus Ltd',
            'country' => 'United Kingdom',
            'purpose' => 'The waitlist and the newsletter',
            'safeguard' => 'Adequacy decision of the European Commission',
            'scope' => ['platform'],
        ],
        [
            'name' => 'Plausible Insights OÜ',
            'country' => 'Estonia',
            'purpose' => 'Audience statistics without cookies, through a Cloudflare Worker proxy, without personal data stored',
            'safeguard' => 'European Union',
            'scope' => ['platform'],
        ],
    ],
];
