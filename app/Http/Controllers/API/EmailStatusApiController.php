<?php

namespace App\Http\Controllers\API;

use App\Contracts\Mail\LiftsEmailSuppressions;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmailStatusResource;
use App\Models\User;
use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The email status of the current member (#1478, ADR 0054): whether the notification email of the address is suppressed
 * after a report of the mail provider, why and since when, and the state of each notification email group. Own state
 * only. The mail that a person asks for (the sign-in link, the password reset) still goes to a suppressed address.
 */
class EmailStatusApiController extends Controller
{
    use ApiResponses;

    /**
     * The email status. `suppressed`: the notification email of the address is stopped. `reason`: `hard_bounce`,
     * `complaint`, `soft_bounces` (3 in a row) or `provider_list`, null when not suppressed. `since`: when it stopped.
     * `address_hint`: the address of the account. `email_choices`: each group with its state (`default`, or `off` after
     * the stop link of a mail). 1 or 2 soft bounces show as not suppressed.
     *
     * @throws AuthenticationException
     */
    final public function show(Request $request): JsonResponse
    {
        return self::success('Email status', null, self::user($request)->toResource(EmailStatusResource::class));
    }

    /**
     * "Try this address again": the notification email of the address goes again, and the mail provider is asked, best
     * effort, to take the address off its list. A complaint is lifted too. Once an hour per account (429 after). Answers
     * with the email status.
     *
     * @throws AuthenticationException
     */
    final public function destroySuppression(Request $request): JsonResponse
    {
        $user = self::user($request);
        app(LiftsEmailSuppressions::class)->lift($user);

        return self::success('Email suppression lifted', null, $user->toResource(EmailStatusResource::class));
    }

    /**
     * @throws AuthenticationException
     */
    private static function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
