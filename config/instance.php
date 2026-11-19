<?php

use App\Constants;

/*
 * The optional values of one instance (#1653, ADR 0055): the publisher, the footer and the social links, the structured
 * data and the first user of the seeder. Each value is trimmed text. An empty value hides what it feeds: no default names
 * a publisher, a link or an address of one instance. The two required values (the operator and its contact address) are in
 * config/legal.php. App\Support\InstanceValues reads this file and parses the two lists.
 */

$text = static function (string $name, string $default = ''): string {
    $value = env($name);
    $value = is_string($value) ? trim($value) : '';

    return $value !== '' ? $value : $default;
};

return [
    'publisher' => [
        // The footer shows "© <year> <name>." and the structured data names it as the publisher. Empty: neither
        'name' => $text('INSTANCE_PUBLISHER_NAME'),

        // The site of the publisher (https), for the structured data. Empty: the publisher has no address there
        'url' => $text('INSTANCE_PUBLISHER_URL'),
    ],

    'footer' => [
        // A short text after the "©" text of the footer, for example "Made in Ireland." Empty: not shown
        'note' => $text('INSTANCE_FOOTER_NOTE'),

        // More links of the "Community" column of the footer: entries "Label=https://address", separated by commas
        // (an address cannot hold a comma). Empty: no extra link
        'links' => $text('INSTANCE_FOOTER_LINKS'),
    ],

    // The icon links of the footer, and "sameAs" of the publisher in the structured data: entries "key=https://address",
    // separated by commas, where social_link_<key> is in Constants::SOCIAL_MEDIA_LINK_PROPERTIES, for example
    // "github=https://github.com/example,linkedin=https://social.example.com/example". Empty: no icon
    'social_links' => $text('INSTANCE_SOCIAL_LINKS'),

    'seed' => [
        // The address of the first user that the database seeder creates (development and tests only)
        'admin_email' => $text('SEED_ADMIN_EMAIL', Constants::INSTANCE_DEFAULT_SEED_ADMIN_EMAIL),
    ],
];
