<?php

namespace App\Http\Middleware;

use App\Support\InstanceValues;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * In production, an instance without an operator or a contact address answers nothing but a 503 that names the values
 * to set (#1653, ADR 0055). It runs before every middleware of the application: every address answers so, the health
 * address too, so that a deployment that waits for a healthy application fails instead of serving pages that name nobody.
 * The log gets the message once a minute, not once for each refused request.
 *
 * The same answer, in every environment except the local one, for an instance whose APP_URL is not a full address: the
 * application takes its own host name from it (AnswerOnlyForOwnHostNames).
 */
class RefuseUnconfiguredInstance
{
    /**
     * The answer for an instance whose APP_URL is empty or has no scheme
     */
    public const string NO_ADDRESS_MESSAGE = 'This instance is not configured. Set APP_URL to the full public address, for example https://app.example.com. See SELF_HOSTING.md.';

    /**
     * @param  Closure(Request): Response  $next
     */
    final public function handle(Request $request, Closure $next): Response
    {
        $message = self::reason();
        if ($message === null) {
            return $next($request);
        }

        if (self::firstOfTheMinute()) {
            Log::critical($message);
        }

        return response($message, 503, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * The text for the operator when the instance is not configured, or null when it is
     */
    private static function reason(): ?string
    {
        $missing = InstanceValues::missing();
        if ($missing !== []) {
            return InstanceValues::notConfiguredMessage($missing);
        }

        if (! app()->isLocal() && AnswerOnlyForOwnHostNames::ownName() === null) {
            return self::NO_ADDRESS_MESSAGE;
        }

        return null;
    }

    /**
     * True for the first refused request of a minute. Without a cache that answers, every request is the first: the
     * message must reach the log
     */
    private static function firstOfTheMinute(): bool
    {
        try {
            return Cache::add('instance-values:refused', true, 60);
        } catch (Throwable) {
            return true;
        }
    }
}
