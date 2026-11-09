<?php

namespace App\Http\Controllers\API;

use App\Contracts\Waitlist\JoinsWaitlist;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WaitlistApiController extends Controller
{
    use ApiResponses;

    /**
     * Add an email address to the waitlist. Public endpoint (rate limited).
     */
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
