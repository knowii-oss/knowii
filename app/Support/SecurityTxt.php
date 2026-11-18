<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The security.txt of the instance (#1559, RFC 9116), built from config/security_txt.php.
 */
final class SecurityTxt
{
    public const string PATH = '/.well-known/security.txt';

    /**
     * The validity of the file when the configuration gives no date
     */
    private const int DEFAULT_VALIDITY_DAYS = 180;

    /**
     * The schemes that a Contact line may use (RFC 9116, section 2.5.3)
     */
    private const array CONTACT_SCHEMES = ['mailto:', 'https://', 'tel:'];

    /**
     * The file, or null when the instance names no valid contact (the Contact field is required)
     */
    public static function txt(): ?string
    {
        $contacts = self::contacts();
        if ($contacts === []) {
            return null;
        }

        $lines = [];
        foreach ($contacts as $contact) {
            $lines[] = 'Contact: '.$contact;
        }
        $lines[] = 'Expires: '.self::expires()->format('Y-m-d\TH:i:s\Z');

        foreach (['encryption' => 'Encryption', 'acknowledgments' => 'Acknowledgments'] as $key => $field) {
            $value = self::httpsUrl(self::configText('security_txt.'.$key));
            if ($value !== null) {
                $lines[] = $field.': '.$value;
            }
        }

        $languages = trim(self::configText('security_txt.preferred_languages'));
        if ($languages !== '') {
            $lines[] = 'Preferred-Languages: '.$languages;
        }

        $lines[] = 'Canonical: '.url(self::PATH);
        $lines[] = 'Policy: '.(self::httpsUrl(self::configText('security_txt.policy')) ?? route('security.show'));

        return implode("\n", $lines)."\n";
    }

    /**
     * The configured contacts that use an allowed scheme, in their order. Another value is left out and logged.
     *
     * @return list<string>
     */
    private static function contacts(): array
    {
        $configured = config('security_txt.contacts', []);
        if (! is_array($configured)) {
            return [];
        }

        $contacts = [];
        foreach ($configured as $contact) {
            $contact = is_string($contact) ? trim($contact) : '';
            if ($contact === '') {
                continue;
            }
            if (! self::startsWithAny($contact, self::CONTACT_SCHEMES) || preg_match('/\s/', $contact) === 1) {
                Log::warning('security.txt: a contact is left out because it is not a mailto:, https:// or tel: address', ['contact' => $contact]);

                continue;
            }
            $contacts[] = $contact;
        }

        return $contacts;
    }

    /**
     * The configured date in UTC, or 180 days after the current day when none is set or it cannot be read
     */
    private static function expires(): CarbonImmutable
    {
        $configured = trim(self::configText('security_txt.expires'));
        if ($configured !== '') {
            try {
                return CarbonImmutable::parse($configured)->utc();
            } catch (Throwable) {
                Log::warning('security.txt: SECURITY_TXT_EXPIRES is not a date; the default is used', ['expires' => $configured]);
            }
        }

        return CarbonImmutable::now('UTC')->startOfDay()->addDays(self::DEFAULT_VALIDITY_DAYS);
    }

    private static function httpsUrl(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' && str_starts_with($value, 'https://') && preg_match('/\s/', $value) !== 1 ? $value : null;
    }

    /**
     * A value of the configuration as text: empty when it is not set or not a string
     */
    private static function configText(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    /**
     * @param  list<string>  $prefixes
     */
    private static function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with(strtolower($value), $prefix)) {
                return true;
            }
        }

        return false;
    }
}
