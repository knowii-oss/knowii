<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\Users\ChangesEmailAddresses;
use App\Enums\KnowiiEmailChangeResult;
use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A change of the email address applies after the new address is confirmed (#1706, ADR 0058): the link of the mail, and
 * the two buttons of the profile form ("Send the link again", "Cancel the change"). The API has the same two buttons
 * (UserApiController). The action does the work.
 */
class EmailChangeController extends Controller
{
    /**
     * The link of the mail. The route is signed and temporary, and needs a session of the account of the link: another
     * account gets 403, as with the link of the address check. The person lands on the profile page with the result.
     *
     * @throws AuthenticationException
     */
    final public function confirm(Request $request, string $userCuid, string $hash): RedirectResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        abort_unless(hash_equals($user->cuid, $userCuid), 403);

        $result = app(ChangesEmailAddresses::class)->confirm($user, $hash);

        return redirect()->route('profile.show')->with('flash', [
            'banner' => $result->message(),
            'bannerStyle' => $result === KnowiiEmailChangeResult::Confirmed ? 'success' : 'danger',
        ]);
    }

    /**
     * "Send the link again": a new link goes to the pending address. No password: the change was confirmed with it already
     *
     * @throws AuthenticationException
     */
    final public function resend(Request $request): RedirectResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        try {
            app(ChangesEmailAddresses::class)->resend($user);
        } catch (ValidationException $e) {
            return back()->with('flash', ['banner' => $e->getMessage(), 'bannerStyle' => 'danger']);
        }

        return back()->with('flash', ['banner' => __('We sent the link again.'), 'bannerStyle' => 'success']);
    }

    /**
     * "Cancel the change": the pending address goes, the account keeps its address
     *
     * @throws AuthenticationException
     */
    final public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        app(ChangesEmailAddresses::class)->cancel($user);

        return back()->with('flash', ['banner' => __('The change of the email address is cancelled.'), 'bannerStyle' => 'success']);
    }
}
