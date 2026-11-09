<?php

namespace App\Support;

/**
 * The newsletter form of the footer (#938) shows only when the instance configures an EmailOctopus list and the
 * Turnstile keys: a self-hosted instance without them shows no form.
 */
final class Newsletter
{
    public static function isEnabled(): bool
    {
        return filled(config('services.emailoctopus.api_key'))
            && filled(config('services.emailoctopus.newsletter_list_id'))
            && filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    /**
     * The shared Inertia prop: whether the form shows, and the Turnstile site key that it needs
     *
     * @return array{enabled: bool, turnstileSiteKey: string|null}
     */
    public static function pageProp(): array
    {
        $enabled = self::isEnabled();
        $siteKey = config('services.turnstile.site_key');

        return [
            'enabled' => $enabled,
            'turnstileSiteKey' => $enabled && is_string($siteKey) ? $siteKey : null,
        ];
    }
}
