<?php

namespace App\Support;

use App\Http\Middleware\AnswerOnlyForOwnHostNames;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * After the sign-out, the page data of a member cannot be read from the history of the browser, on https. A page tells the
 * browser to encrypt the pages that it keeps in its history (`inertia.history.encrypt`, on by default): the key stays in
 * the storage of the tab. After a sign-out, the next page of the session tells the browser to forget that key
 * (Inertia::clearHistory()): the pages of before cannot be read any more, and "Back" asks the server, which answers a
 * visitor. The web application forgets the key by itself too, in every tab, when a page arrives for another account or
 * for no account (apps/knowii/src/history-account.ts).
 *
 * A browser encrypts only in a secure context, and its links stop when a page asks for an encryption that it cannot do.
 * So a page asks for it only when the request came over https, or to a loopback name (`localhost`, `127.0.0.1`, `[::1]`).
 * On plain http for another host the pages are not encrypted: the links work, and the history of a tab keeps its pages.
 *
 * The callers of the clear: the sign-out, the deletion of the account, "Log out other browser sessions", the end of a
 * session (App\Support\EndedSession) and a takeover of the account (ADR 0059). The mark lives in the session, so a call
 * comes after the session was invalidated, never before.
 */
final class BrowserHistory
{
    /**
     * The pages of the request ask the browser to encrypt its history, or not: call it before a page is rendered
     */
    public static function decideEncryption(Request $request): void
    {
        Inertia::encryptHistory(self::encryptsFor($request));
    }

    /**
     * Whether a page of the request asks the browser to encrypt its history: the instance did not turn it off, and the
     * browser can do it for this address
     */
    public static function encryptsFor(Request $request): bool
    {
        return config('inertia.history.encrypt') === true
            && ($request->isSecure() || in_array(strtolower($request->getHost()), AnswerOnlyForOwnHostNames::LOOPBACK_NAMES, true));
    }

    /**
     * The next page of the session of the request tells the browser to forget the pages that it keeps. A request without
     * a session (a call with an API token) has no browser history to clear
     */
    public static function clear(?Request $request = null): void
    {
        if (($request ?? request())->hasSession()) {
            Inertia::clearHistory();
        }
    }
}
