<?php

namespace App\Broadcasting;

use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The broadcaster of Reverb (ADR 0075): the broadcaster of Pusher, with two rules for the authorization of a channel
 * (`POST /broadcasting/auth`), after the rule of the channel (routes/channels.php) accepts it.
 *
 * 1. Every private channel is signed with the account: its answer holds `channel_data = {"user_id": "<cuid>"}`, as a
 *    presence channel does. The WebSocket server checks that data with the signature and keeps it on the subscription, so it
 *    knows the account of every connection that holds a channel, and ends them all when the access of the account ends
 *    (App\Support\ReverbLiveConnections). A private channel shows its subscribers to nobody: the data stays on the server.
 * 2. One connection belongs to one account: the first account that gets a channel on a connection keeps that connection
 *    for SOCKET_TTL_SECONDS, and a channel for another account on it is refused (403).
 */
final class AccountBoundBroadcaster extends PusherBroadcaster
{
    public const string SOCKET_CACHE_PREFIX = 'live-socket:';

    public const int SOCKET_TTL_SECONDS = 86_400;

    /**
     * The types of the parameters and of the answer are the ones of the parent
     *
     * @throws AccessDeniedHttpException The connection belongs to another account
     * @throws JsonException
     */
    public function validAuthenticationResponse($request, $result)
    {
        $channelName = $request->string('channel_name')->toString();
        $socketId = $request->string('socket_id')->toString();
        $user = $this->retrieveUser($request, $this->normalizeChannelName($channelName));
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException;
        }

        $userCuid = $user->getAuthIdentifierForBroadcasting();
        $key = self::SOCKET_CACHE_PREFIX.$socketId;
        if (! Cache::add($key, $userCuid, self::SOCKET_TTL_SECONDS) && Cache::get($key) !== $userCuid) {
            throw new AccessDeniedHttpException;
        }

        if (! str_starts_with($channelName, 'private')) {
            return parent::validAuthenticationResponse($request, $result);
        }

        return $this->decodePusherResponse(
            $request,
            $this->pusher->authorizeChannel($channelName, $socketId, json_encode(['user_id' => $userCuid], JSON_THROW_ON_ERROR)),
        );
    }
}
