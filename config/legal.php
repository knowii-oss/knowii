<?php

use App\Constants;

/*
|--------------------------------------------------------------------------
| Legal facts of the instance: the operator, its contact address and the folder of its legal texts (#904, #1653)
|--------------------------------------------------------------------------
|
| The code holds no legal text and no sub-processor (ADR 0055). An instance keeps both in the folder 'path':
| storage/app/legal/, which an update never overwrites. It holds one Markdown file for each legal page (policy.md,
| terms.md, dpa.md, imprint.md, content-policy.md, security.md) and subprocessors.json. A page without a file answers
| 404, and what links to it is hidden (App\Support\LegalPages). The folder has no environment name on purpose: every
| instance uses the same place. The repository holds neutral templates for an operator, which nothing serves. The texts
| of the hosted instance are in deploy/, and its deploy copies them. See SELF_HOSTING.md, "Legal pages".
|
| subprocessors.json is a JSON list of objects: 'name', 'country', 'purpose', 'safeguard', 'scope' and, for a
| sub-processor that only an optional feature uses (an AI feature that each owner turns on), 'feature'. The privacy
| policy and the data processing agreement show the list through the {{subprocessors}} placeholder. 'scope' says whose
| data a sub-processor receives (#1352): 'community' for the data of the communities (the operator is the processor: the
| DPA lists it), 'platform' for the data of the accounts, the waitlist, the newsletter and the statistics (the operator
| is the controller: only the privacy policy lists it). Without a scope, both pages list it. A pull request that adds a
| third-party service that receives personal data follows rule 9 of the data rules in
| documentation/conventions/rules.md.
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

    /*
    | Optional: the postal address of the operator, for the footer of a notification email (#1478): "<operator>, <postal
    | address>". Empty: the footer shows the operator only.
    */
    'postal_address' => trim((string) env('LEGAL_POSTAL_ADDRESS', '')),

    /*
    | The folder of the legal texts and of subprocessors.json. No fallback: a file that is not there does not exist.
    */
    'path' => storage_path('app/legal'),
];
