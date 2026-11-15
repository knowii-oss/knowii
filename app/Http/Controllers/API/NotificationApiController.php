<?php

namespace App\Http\Controllers\API;

use App\Constants;
use App\Contracts\Notifications\ListsNotifications;
use App\Contracts\Notifications\MarksNotificationsRead;
use App\Contracts\Notifications\MarksNotificationsSeen;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The notifications of the current member, in every community (#1474, ADR 0042, design 5.1, 5.2, 6.1). Own rows only:
 * the row of another member gives a 404. No endpoint deletes a row. Every read checks the access first: a row that the
 * member cannot view any more is deleted, not listed and not counted. Offset pagination (ADR 0011).
 */
class NotificationApiController extends Controller
{
    use ApiResponses;

    /**
     * The "Needs you" notifications of the member, the newest news first (a bundled row moves up when a new comment
     * arrives). Each one holds the community, the space, the last author, up to 3 authors with `others_count`, a plain
     * text excerpt (300 characters at most) and the title built from the live content, and the target to open.
     * Without `older`, the rows of the last 14 days; with `older=true`, the older ones.
     *
     * @throws AuthenticationException
     */
    #[QueryParameter('limit', description: 'Notifications per page (default 20, maximum 100)', type: 'int', example: 20)]
    #[QueryParameter('offset', description: 'Notifications to skip (default 0)', type: 'int', example: 0)]
    #[QueryParameter('community', description: 'Keep the notifications of this community (cuid)', type: 'string')]
    #[QueryParameter('older', description: 'true: the notifications that are older than 14 days', type: 'bool', example: false)]
    final public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $community = $request->query('community');

        $page = app(ListsNotifications::class)->list(
            $user,
            Pagination::fromRequest($request, Constants::NOTIFICATIONS_PER_PAGE),
            is_string($community) && $community !== '' ? $community : null,
            $request->boolean('older'),
        );

        return self::success('Notifications', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * The number that the bell shows: the "Needs you" notifications that the member did not see, 14 days old at most,
     * after the access check. The client shows 1 to 9, then "9+", and nothing at zero.
     *
     * @throws AuthenticationException
     */
    final public function count(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return self::success('Unseen notifications', null, ['count' => app(ListsNotifications::class)->unseenCount($user)]);
    }

    /**
     * The lines were on screen: mark notifications as seen. A cuid that is not a notification of the member is ignored.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('notification_cuids', description: 'The cuids of 1 to 100 notifications', required: true, type: 'array<string>')]
    final public function seen(Request $request): JsonResponse
    {
        app(MarksNotificationsSeen::class)->seen($request->user() ?? throw new AuthenticationException, $request->only(['notification_cuids']));

        return self::success('Notifications seen');
    }

    /**
     * The line was opened or marked done: the notification is read, and seen. A mention in a chat space also moves the
     * chat cursor of the member forward to its message, never backward.
     *
     * @throws AuthenticationException
     */
    final public function read(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $read = app(MarksNotificationsRead::class)->read($user, $notification);

        return self::success('Notification read', null, app(ListsNotifications::class)->present($user, $read)->toResource());
    }

    /**
     * Undo "read" (the "Done. Undo" of the list): the notification is open again, and still seen. It fails with a
     * business error when a newer notification about the same thing is open already. The chat cursor stays where it is.
     *
     * @throws AuthenticationException
     */
    final public function unread(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $open = app(MarksNotificationsRead::class)->unread($user, $notification);

        return self::success('Notification open again', null, app(ListsNotifications::class)->present($user, $open)->toResource());
    }
}
