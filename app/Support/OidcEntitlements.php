<?php

namespace App\Support;

use App\Constants;
use App\Models\Community;

/**
 * The configuration of the entitlements of the sign-in provider (#1386, ADR 0047): generic, per instance, all optional.
 * The feature is on only when the sign-in of #1385 is on and the address template `OIDC_ENTITLEMENTS_URL` is a safe
 * address with `{subject}` in its path. Without it there is no route, no job, no rule and no screen.
 *
 * Nothing here names a key or a community of any provider: the operator lists the communities (by slug) that may use
 * rules, and the managers of those communities choose the keys.
 */
final class OidcEntitlements
{
    public const string SUBJECT_PLACEHOLDER = '{subject}';

    public const string WEBHOOK_PATH = '/webhooks/oidc/entitlements';

    public static function enabled(): bool
    {
        return OpenIdConnect::enabled() && self::urlTemplate() !== null;
    }

    /**
     * Whether the communities of this instance may use rules at all: the feature is on and the community is listed
     */
    public static function allows(Community $community): bool
    {
        return self::enabled() && in_array($community->slug, self::communitySlugs(), true);
    }

    /**
     * The slugs that the operator listed (empty means none)
     *
     * @return list<string>
     */
    public static function communitySlugs(): array
    {
        $configured = config('services.oidc.entitlement_communities');
        $slugs = [];
        foreach (preg_split('/[\s,]+/', is_string($configured) ? $configured : '', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $slug) {
            if (! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * The address of the read of one subject: the template with the subject in its path, encoded
     */
    public static function urlFor(string $subject): ?string
    {
        $template = self::urlTemplate();

        return $template === null ? null : str_replace(self::SUBJECT_PLACEHOLDER, rawurlencode($subject), $template);
    }

    /**
     * The scope of the client credentials grant (the provider gives it to confidential clients that it registered for it)
     */
    public static function scope(): string
    {
        return self::setting('entitlements_scope') ?? 'entitlements:read:subjects';
    }

    /**
     * The audience or resource parameter of the token request, when the provider needs one
     */
    public static function resource(): ?string
    {
        return self::setting('entitlements_resource');
    }

    /**
     * The scope that the sign-in asks for when the feature is on: the provider links a client to a person only then
     */
    public static function signInScope(): ?string
    {
        return self::enabled() ? (self::setting('entitlements_sign_in_scope') ?? 'entitlements:read') : null;
    }

    /**
     * Whether a key is acceptable: letters, digits, dot, dash and underscore, at most 100 characters
     */
    public static function isValidKey(string $key): bool
    {
        return $key !== '' && strlen($key) <= Constants::MAX_LENGTH_ENTITLEMENT_KEY && preg_match(Constants::ENTITLEMENT_KEY_PATTERN, $key) === 1;
    }

    /**
     * The template when it is usable, else null. The subject must be in the path: a subject in the host or in the scheme
     * could point the server at another host. The address is checked like the endpoints of the provider.
     */
    private static function urlTemplate(): ?string
    {
        $template = self::setting('entitlements_url');
        if ($template === null || substr_count($template, self::SUBJECT_PLACEHOLDER) !== 1) {
            return null;
        }

        $first = parse_url(str_replace(self::SUBJECT_PLACEHOLDER, 'a1', $template));
        $second = parse_url(str_replace(self::SUBJECT_PLACEHOLDER, 'b2', $template));
        if ($first === false || $second === false || ! OpenIdConnect::isAllowedAddress($template)) {
            return null;
        }
        // Whatever replaces the placeholder, the scheme, host and port stay the same, and there is no query to inject into
        foreach (['scheme', 'host', 'port'] as $part) {
            if (($first[$part] ?? null) !== ($second[$part] ?? null)) {
                return null;
            }
        }
        if (! isset($first['path']) || isset($first['query'])) {
            return null;
        }

        return $template;
    }

    private static function setting(string $key): ?string
    {
        $value = config('services.oidc.'.$key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
