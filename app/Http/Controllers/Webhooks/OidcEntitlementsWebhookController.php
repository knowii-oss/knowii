<?php

namespace App\Http\Controllers\Webhooks;

use App\Contracts\Entitlements\ReceivesEntitlementEvents;
use App\Exceptions\OpenIdConnectException;
use App\Http\Controllers\Controller;
use App\Jobs\ReadEntitlements;
use App\Models\UserIdentity;
use App\Support\OidcEntitlements;
use App\Support\OpenIdConnect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The change call of the sign-in provider (#1386, ADR 0047): "something changed for this subject". Public, with no session,
 * no CSRF token and no token ability (the proof is the signature, see `ReceiveEntitlementEvent`), and limited per IP address.
 *
 * - Only `Content-Type: application/jwt` is accepted.
 * - A call that is not valid in any way answers 401 with an empty body: no detail, so that nobody learns what was wrong.
 * - A valid call answers 202 and queues the read of the subject, when an identity with this issuer and subject exists. When
 *   none exists the answer is the same 202 and nothing else happens: no one can learn who has an account here.
 * - A call that repeats a `jti` that was handled (the provider retries with the same one) answers 202 and does nothing.
 * - The call is never trusted for more than the subject: the keys come from the read of the truth.
 *
 * The log holds the reason and the issuer, never a token, a subject or a key.
 */
class OidcEntitlementsWebhookController extends Controller
{
    private const string CONTENT_TYPE = 'application/jwt';

    final public function store(Request $request, ReceivesEntitlementEvents $events): Response
    {
        abort_unless(OidcEntitlements::enabled(), 404);

        if (self::mediaType($request) !== self::CONTENT_TYPE) {
            Log::info('Entitlements: a change call was refused', ['reason' => 'wrong_content_type', 'issuer' => OpenIdConnect::issuer()]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $subject = $events->receive(trim($request->getContent()));
        } catch (OpenIdConnectException $e) {
            Log::info('Entitlements: a change call was refused', ['reason' => $e->reason, 'issuer' => OpenIdConnect::issuer()]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        if ($subject !== null) {
            $identity = UserIdentity::query()->where('issuer', OpenIdConnect::issuer() ?? '')->where('subject', $subject)->first();
            if ($identity !== null) {
                ReadEntitlements::dispatch($identity->id);
            }
        }

        return response('', Response::HTTP_ACCEPTED);
    }

    /**
     * The media type of the request without its parameters, in lower case
     */
    private static function mediaType(Request $request): string
    {
        return strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'))[0]));
    }
}
