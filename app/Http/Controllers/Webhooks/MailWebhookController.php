<?php

namespace App\Http\Controllers\Webhooks;

use App\Constants;
use App\Contracts\Mail\ReceivesMailWebhooks;
use App\Exceptions\MailWebhookException;
use App\Http\Controllers\Controller;
use App\Support\MailWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The webhook of the mail provider (#1478, ADR 0054): bounces, complaints, the list of the provider and the deliveries.
 * Public, with no session, no CSRF token and no token ability (the proof is the signature of the call), and limited per IP
 * address.
 *
 * - 404 to every call when the instance has no usable secret (RESEND_WEBHOOK_SECRET): no check is made.
 * - 413 with an empty body for a body over Constants::MAIL_WEBHOOK_MAX_BODY_BYTES, before any check.
 * - 401 with an empty body for any call that is not valid (a missing or malformed header, a signature or a time that is not
 *   valid): no detail, so that nobody learns what was wrong.
 * - 202 with an empty body for a valid call, also for an event that was handled, an event type that the platform ignores
 *   and a person that it does not know: nobody can learn who has an account here. The work runs in a queued job.
 */
class MailWebhookController extends Controller
{
    final public function store(Request $request, ReceivesMailWebhooks $webhooks): Response
    {
        $key = MailWebhookSignature::secret();
        abort_if($key === null, 404);

        // A real report is a few KB: a larger body is refused before it is hashed
        if (strlen($request->getContent()) > Constants::MAIL_WEBHOOK_MAX_BODY_BYTES) {
            return response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $webhooks->receive(
                $key,
                $request->getContent(),
                self::header($request, MailWebhookSignature::HEADER_ID),
                self::header($request, MailWebhookSignature::HEADER_TIMESTAMP),
                self::header($request, MailWebhookSignature::HEADER_SIGNATURE),
            );
        } catch (MailWebhookException $e) {
            Log::info('Mail provider: a webhook call was refused', ['reason' => $e->reason]);

            return response('', $e->status);
        }

        return response('', Response::HTTP_ACCEPTED);
    }

    /**
     * A header as a plain string, or null when it is missing or empty
     */
    private static function header(Request $request, string $name): ?string
    {
        $value = $request->headers->get($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
