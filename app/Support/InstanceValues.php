<?php

namespace App\Support;

use App\Constants;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The values of one instance (#1653, ADR 0055): who runs it, how to reach them, and what its footer and its structured
 * data show. They come from config/legal.php (the two required values) and config/instance.php (the optional ones), never
 * from the code. An optional value that is empty hides what it feeds. A wrong value is left out and logged.
 */
final class InstanceValues
{
    /**
     * What an instance that is not configured answers and prints. `:names` is the list of the environment names to set
     */
    public const string NOT_CONFIGURED_MESSAGE = 'This instance is not configured. Set :names in the environment. See SELF_HOSTING.md, section "Instance values".';

    /**
     * The description of the platform in the structured data
     */
    private const string DESCRIPTION = 'Where all communities belong. A place for your Knowledge, your Ideas and Inspiration.';

    /**
     * The prefix of a social link property (Constants::SOCIAL_MEDIA_LINK_PROPERTIES): the configuration gives the rest
     */
    private const string SOCIAL_LINK_PREFIX = 'social_link_';

    /**
     * Who runs this instance. Empty when it is not set
     */
    public static function operator(): string
    {
        return self::text('legal.operator');
    }

    /**
     * The postal address of the operator, for the footer of a notification email (#1478). Empty when it is not set
     */
    public static function postalAddress(): string
    {
        return self::text('legal.postal_address');
    }

    /**
     * The public contact address of the operator. Empty when it is not set
     */
    public static function contactEmail(): string
    {
        return self::text('legal.contact_email');
    }

    /**
     * The environment names of the required values that are missing. Only production requires them: elsewhere the list is
     * empty. In production a value is missing when it is empty, when it is the placeholder of the other environments in
     * any letter case (a copied example file must not pass), or, for the address, when it is not an email address.
     *
     * @return list<string>
     */
    public static function missing(): array
    {
        if (! app()->isProduction()) {
            return [];
        }

        $missing = [];

        $operator = self::operator();
        if ($operator === '' || strcasecmp($operator, Constants::INSTANCE_PLACEHOLDER_OPERATOR) === 0) {
            $missing[] = 'LEGAL_OPERATOR';
        }

        $contactEmail = self::contactEmail();
        if ($contactEmail === '' || strcasecmp($contactEmail, Constants::INSTANCE_PLACEHOLDER_CONTACT_EMAIL) === 0 || filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            $missing[] = 'LEGAL_CONTACT_EMAIL';
        }

        return $missing;
    }

    /**
     * The text that tells the operator which values to set
     *
     * @param  list<string>  $missing
     */
    public static function notConfiguredMessage(array $missing): string
    {
        return str_replace(':names', implode(' and ', $missing), self::NOT_CONFIGURED_MESSAGE);
    }

    /**
     * The name of the publisher, or null when the instance names none
     */
    public static function publisherName(): ?string
    {
        $name = self::text('instance.publisher.name');

        return $name !== '' ? $name : null;
    }

    /**
     * The site of the publisher, or null when the instance gives none (or gives an address that is not https)
     */
    public static function publisherUrl(): ?string
    {
        $url = self::text('instance.publisher.url');
        if ($url === '') {
            return null;
        }
        if (! self::isHttpsUrl($url)) {
            self::warnOnce('Instance values: INSTANCE_PUBLISHER_URL is left out because it is not an https:// address', $url);

            return null;
        }

        return $url;
    }

    /**
     * The text after the "©" text of the footer, or null when the instance gives none
     */
    public static function footerNote(): ?string
    {
        $note = self::text('instance.footer.note');

        return $note !== '' ? $note : null;
    }

    /**
     * The extra links of the "Community" column of the footer, in their order: entries "Label=https://address". An entry
     * with an empty label, an address that is not https or the label of an entry before it is left out and logged.
     *
     * @return list<array{label: string, href: string}>
     */
    public static function footerLinks(): array
    {
        $links = [];
        foreach (self::entries('instance.footer.links') as $entry) {
            if ($entry['key'] === '' || ! self::isHttpsUrl($entry['value'])) {
                self::warnOnce('Instance values: a footer link is left out because it is not "Label=https://address"', $entry['entry']);

                continue;
            }
            if (isset($links[$entry['key']])) {
                self::warnOnce('Instance values: a footer link is left out because another link has the same label', $entry['entry']);

                continue;
            }
            $links[$entry['key']] = ['label' => $entry['key'], 'href' => $entry['value']];
        }

        return array_values($links);
    }

    /**
     * The social links of the instance, in their order: entries "key=https://address", where social_link_<key> is a
     * property of Constants::SOCIAL_MEDIA_LINK_PROPERTIES. An entry with an unknown key or an address that is not https,
     * and an entry that is in the list twice, is left out and logged.
     *
     * @return list<array{property: string, href: string}>
     */
    public static function socialLinks(): array
    {
        $links = [];
        foreach (self::entries('instance.social_links') as $entry) {
            $property = self::SOCIAL_LINK_PREFIX.strtolower($entry['key']);
            if (! in_array($property, Constants::SOCIAL_MEDIA_LINK_PROPERTIES, true) || ! self::isHttpsUrl($entry['value'])) {
                self::warnOnce('Instance values: a social link is left out because it is not "key=https://address" with a known key', $entry['entry']);

                continue;
            }
            $link = ['property' => $property, 'href' => $entry['value']];
            if (in_array($link, $links, true)) {
                self::warnOnce('Instance values: a social link is left out because it is in the list twice', $entry['entry']);

                continue;
            }
            $links[] = $link;
        }

        return $links;
    }

    /**
     * The address of the first user of the database seeder
     */
    public static function seedAdminEmail(): string
    {
        $email = self::text('instance.seed.admin_email');

        return $email !== '' ? $email : Constants::INSTANCE_DEFAULT_SEED_ADMIN_EMAIL;
    }

    /**
     * The shared Inertia prop: what the footer, the contact page and the account deletion texts show
     *
     * @return array{contactEmail: string, publisher: string|null, footerNote: string|null, footerLinks: list<array{label: string, href: string}>, socialLinks: list<array{property: string, href: string}>}
     */
    public static function pageProp(): array
    {
        return [
            'contactEmail' => self::contactEmail(),
            'publisher' => self::publisherName(),
            'footerNote' => self::footerNote(),
            'footerLinks' => self::footerLinks(),
            'socialLinks' => self::socialLinks(),
        ];
    }

    /**
     * The structured data of the site (JSON-LD, schema.org). The publisher is there only when the instance names one
     *
     * @return array<string, string|array<string, string|list<string>>>
     */
    public static function structuredData(): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => self::text('app.name'),
            'description' => self::DESCRIPTION,
            'url' => self::text('app.url'),
        ];

        $publisherName = self::publisherName();
        if ($publisherName === null) {
            return $data;
        }

        $publisher = ['@type' => 'Organization', 'name' => $publisherName];
        $publisherUrl = self::publisherUrl();
        if ($publisherUrl !== null) {
            $publisher['url'] = $publisherUrl;
        }
        $sameAs = array_column(self::socialLinks(), 'href');
        if ($sameAs !== []) {
            $publisher['sameAs'] = $sameAs;
        }
        $data['publisher'] = $publisher;

        return $data;
    }

    /**
     * The entries of a list value, "key=value" separated by commas, split at the first "=". Empty entries are skipped. A
     * part without "=" is the rest of the entry before it, cut at a comma of its address ("Docs=https://example.com/a,b"):
     * both go back together as one entry with an empty key, so the caller leaves it out and never shows half an address.
     * A comma in an address is written "%2C".
     *
     * @return list<array{entry: string, key: string, value: string}>
     */
    private static function entries(string $configKey): array
    {
        $entries = [];
        foreach (explode(',', self::text($configKey)) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (! str_contains($entry, '=')) {
                $before = array_pop($entries);
                $entries[] = ['entry' => ($before === null ? '' : $before['entry'].',').$entry, 'key' => '', 'value' => ''];

                continue;
            }
            $parts = explode('=', $entry, 2);
            $entries[] = ['entry' => $entry, 'key' => trim($parts[0]), 'value' => trim($parts[1] ?? '')];
        }

        return $entries;
    }

    /**
     * One warning a day for one wrong value, not one for each page: every page reads these values. Without a cache that
     * answers, nothing is logged here: the page must still render.
     */
    private static function warnOnce(string $message, string $value): void
    {
        try {
            if (Cache::add('instance-values:warned:'.hash('sha256', $message.'|'.$value), true, 86400)) {
                Log::warning($message, ['value' => $value]);
            }
        } catch (Throwable) {
            // The cache does not answer: the value is still left out
        }
    }

    /**
     * An https address with a host and no white space
     */
    private static function isHttpsUrl(string $value): bool
    {
        return str_starts_with($value, 'https://') && preg_match('/\s/', $value) !== 1 && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * A value of the configuration as trimmed text: empty when it is not set or not a string
     */
    private static function text(string $configKey): string
    {
        $value = config($configKey);

        return is_string($value) ? trim($value) : '';
    }
}
