<?php

namespace App\Http\Controllers\API;

use App\Contracts\Waitlist\JoinsWaitlist;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WaitlistApiController extends Controller
{
    use ApiResponses;

    /**
     * Add an email address to the waitlist. Public endpoint (rate limited).
     */
    #[BodyParameter('email', description: 'Email address to add (a confirmation email follows: double opt-in)', required: true, type: 'string', format: 'email', example: 'ada@example.com')]
    #[BodyParameter('turnstileToken', description: 'Cloudflare Turnstile token from the widget on the page', required: true, type: 'string')]
    final public function join(Request $request): JsonResponse
    {
        Log::info('Processing API request to join the waitlist.');

        app(JoinsWaitlist::class)->join($request->all(), $request->ip());

        $message = config('services.emailoctopus.waitlist_contact_status') === 'subscribed'
            ? 'You are on the waitlist. Thank you!'
            : 'Almost there! Check your inbox to confirm your email address.';

        return self::success($message);
    }
}
