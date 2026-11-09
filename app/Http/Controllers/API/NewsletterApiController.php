<?php

namespace App\Http\Controllers\API;

use App\Contracts\Newsletter\SubscribesToNewsletter;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NewsletterApiController extends Controller
{
    use ApiResponses;

    /**
     * Subscribe an email address to the newsletter. Public endpoint (rate limited); it fails when the instance does not
     * configure a newsletter list.
     */
    #[BodyParameter('email', description: 'Email address to subscribe (a confirmation email follows: double opt-in)', required: true, type: 'string', format: 'email', example: 'ada@example.com')]
    #[BodyParameter('turnstileToken', description: 'Cloudflare Turnstile token from the widget on the page', required: true, type: 'string')]
    final public function subscribe(Request $request): JsonResponse
    {
        Log::info('Processing API request to subscribe to the newsletter.');

        app(SubscribesToNewsletter::class)->subscribe($request->all(), $request->ip());

        $message = config('services.emailoctopus.newsletter_contact_status') === 'subscribed'
            ? 'You are subscribed. Thank you!'
            : 'Almost there! Check your inbox to confirm your email address.';

        return self::success($message);
    }
}
