<?php

namespace App\Http\Controllers\Mail;

use App\Contracts\Notifications\StopsEmailGroups;
use App\Enums\KnowiiNotificationEmailGroup;
use App\Http\Controllers\Controller;
use App\Models\NotificationEmailChoice;
use App\Models\User;
use App\Support\MailStopLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The stop link of a notification email (#1478, ADR 0054, design 6.13 point 5, RFC 8058). Three routes without a sign-in,
 * each limited per IP address before the check of the signature, which is the only proof:
 *
 * - GET `mail.stop.show`: the page. It changes nothing (mail scanners open every link): the confirm state with one button,
 *   the done state when the group is off already or after the stop of the page, the undone state after the undo.
 * - POST `mail.stop.store`: the stop. The one-click POST of a mail program (`List-Unsubscribe=One-Click`, no session, no
 *   CSRF token) answers 200 with an empty body. The POST of the button of the page (`source=page`) answers a 303 to the
 *   page in its done state.
 * - POST `mail.stop.undo`: the undo, with the state of the signed `restore`, then a 303 to the page in its undone state.
 *
 * A signature that is not valid answers 403: the page in its "not valid" state for the GET, an empty body for a POST
 * (bootstrap/app.php). A valid link of an account that does not exist gives the answers of a live account and writes
 * nothing: nobody learns who has an account. The page names no person, no address and no community.
 */
class MailStopController extends Controller
{
    public const string PAGE = 'Mail/Stop';

    final public function show(Request $request): Response
    {
        $group = self::group($request);
        $cuid = self::userCuid($request);
        if ($group === null || $cuid === null) {
            return self::invalid($request);
        }

        $state = match ($request->query('result')) {
            MailStopLink::RESULT_STOPPED => 'done',
            MailStopLink::RESULT_UNDONE => 'undone',
            default => self::isOff($cuid, $group) ? 'done' : 'confirm',
        };

        return Inertia::render(self::PAGE, [
            'state' => $state,
            'group' => $group->value,
            'stopUrl' => $state === 'confirm' ? MailStopLink::address($cuid, $group) : null,
            'doneUrl' => $state === 'confirm' ? MailStopLink::done($cuid, $group) : null,
            'undoUrl' => $state === 'done' ? MailStopLink::undo($cuid, $group) : null,
            'undoneUrl' => $state === 'done' ? MailStopLink::undone($cuid, $group) : null,
        ])->toResponse($request);
    }

    final public function store(Request $request, StopsEmailGroups $stops): Response
    {
        $group = self::group($request);
        $cuid = self::userCuid($request);
        if ($group === null || $cuid === null) {
            return self::invalid($request);
        }

        $user = User::query()->where('cuid', $cuid)->first();
        if ($user !== null) {
            $stops->stop($user, $group);
        }

        return $request->input('source') === 'page'
            ? redirect()->to(MailStopLink::done($cuid, $group), Response::HTTP_SEE_OTHER)
            : response('', Response::HTTP_OK);
    }

    final public function undo(Request $request, StopsEmailGroups $stops): Response
    {
        $group = self::group($request);
        $cuid = self::userCuid($request);
        $restore = $request->query('restore');
        if ($group === null || $cuid === null || $restore !== NotificationEmailChoice::STATE_DEFAULT) {
            return self::invalid($request);
        }

        $user = User::query()->where('cuid', $cuid)->first();
        if ($user !== null) {
            $stops->restore($user, $group, $restore);
        }

        return redirect()->to(MailStopLink::undone($cuid, $group), Response::HTTP_SEE_OTHER);
    }

    /**
     * The answer to a link that is not valid: the page in its "not valid" state for a GET, an empty body for a POST
     */
    public static function invalid(Request $request): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return response('', Response::HTTP_FORBIDDEN);
        }

        return Inertia::render(self::PAGE, [
            'state' => 'invalid',
            'group' => null,
            'stopUrl' => null,
            'doneUrl' => null,
            'undoUrl' => null,
            'undoneUrl' => null,
        ])->toResponse($request)->setStatusCode(Response::HTTP_FORBIDDEN);
    }

    private static function group(Request $request): ?KnowiiNotificationEmailGroup
    {
        $value = $request->query('group');

        return is_string($value) ? KnowiiNotificationEmailGroup::tryFrom($value) : null;
    }

    private static function userCuid(Request $request): ?string
    {
        $value = $request->query('user');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function isOff(string $cuid, KnowiiNotificationEmailGroup $group): bool
    {
        return NotificationEmailChoice::query()
            ->where('group', $group->value)
            ->where('state', NotificationEmailChoice::STATE_OFF)
            ->whereIn('user_id', User::query()->where('cuid', $cuid)->select('id'))
            ->exists();
    }
}
