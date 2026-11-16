import type { MouseEvent, ReactNode } from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { Notification } from '@knowii/common';
import { knowiiApiClient, NOTIFICATIONS_PER_PAGE, NOTIFICATIONS_URL, USER_NOTIFICATIONS_URL, useTypedPage } from '@knowii/common';
import { Link } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { FaChevronDown, FaChevronRight } from 'react-icons/fa6';
import { Button, EmptyState } from '@/Components/ui';
import { clearCatchUp, useActiveCatchUp } from './catch-up-state';
import type { CatchUpCommunity } from './CatchUpCard';
import { CatchUpCard } from './CatchUpCard';
import { NotificationLine } from './NotificationLine';
import { notificationHref } from './notification-link';
import { threadOf } from './notification-thread';
import { useNotifications } from './NotificationsProvider';
import type { NotificationsChange } from './NotificationsProvider';
import { useNotificationFeed } from './use-notification-feed';

// A line that was visible for one second is seen (design 6.1)
export const SEEN_AFTER_MS = 1000;
// "Done. Undo" stays 8 seconds (design 5.1.7)
export const UNDO_MS = 8000;
// The closing of the panel takes a moment: a jump inside the page waits for it
export const SAME_PAGE_JUMP_DELAY_MS = 260;
// The events that this tab caused are not news
const OWN_CHANGE_MS = 5000;
const SKELETON_LINES = 4;
const FOLLOWING_SKELETON_LINES = 2;

export interface NotificationsPanelProps {
  // Close the panel (a line was opened)
  onClose: () => void;
  // Give the focus to the heading "Notifications" of the panel (or the main heading of the page)
  focusHeading: () => void;
  /**
   * The communities whose "Catch up" card shows above the lists (#1476): the community of the page for the panel, every
   * community of the member for the page "Notifications". The card shows only when `catchUp` holds a date and the community
   * has lines.
   */
  catchUpCommunities?: CatchUpCommunity[];
  /**
   * panel (default): in the bell, the heading levels start at 3 and the link "Open the Notifications page" ends the list.
   * page: the page "Notifications" (#1476), the headings start at 2.
   */
  variant?: 'panel' | 'page';
}

interface UndoState {
  notification: Notification;
  index: number;
  older: boolean;
}

// "Muted. Undo": what to do to take the mute back (#1497)
interface MuteState {
  undo: () => Promise<boolean>;
}

// The server tells every tab about a change, the tab that caused it included: it ignores its own changes for a moment
function markOwnChange(changes: Map<string, number>, cuid: string): void {
  changes.set(cuid, Date.now());
}

type FocusRequest = { cuid: string } | 'heading' | 'following-heading' | 'needs-you-heading';

/**
 * The content of the Notifications panel: "Needs you" with its lines, "Older" and the status text (#1484, design 5.1, 9.1).
 * It mounts when the panel opens, so each opening loads the list again.
 */
export function NotificationsPanel({ onClose, focusHeading, catchUpCommunities = [], variant = 'panel' }: NotificationsPanelProps) {
  const Heading = variant === 'page' ? 'h2' : 'h3';
  const route = useRoute();
  const page = useTypedPage();
  const { refreshCount, subscribe } = useNotifications();
  const showCommunity = (page.props.communityCount ?? 1) > 1;
  const catchUpSince = useActiveCatchUp(page.props.catchUp?.since);

  const main = useNotificationFeed();
  const [olderOpen, setOlderOpen] = useState(false);
  const older = useNotificationFeed({ older: true, enabled: olderOpen });
  // "Following" (level 2): the last 14 days, never counted, never announced (#1497)
  const following = useNotificationFeed({ following: true });

  const [busy, setBusy] = useState<ReadonlySet<string>>(new Set());
  const [doneElsewhere, setDoneElsewhere] = useState<ReadonlySet<string>>(new Set());
  const [undo, setUndo] = useState<UndoState | null>(null);
  const [muted, setMuted] = useState<MuteState | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  // Lines that arrived while the panel is open wait behind "2 new. Show"
  const [incoming, setIncoming] = useState<{ items: Notification[]; fresh: Notification[]; nextOffset: number | null } | null>(null);

  const lineElements = useRef(new Map<string, HTMLLIElement>());
  const linkElements = useRef(new Map<string, HTMLAnchorElement>());
  const retryButton = useRef<HTMLButtonElement>(null);
  const undoButton = useRef<HTMLButtonElement>(null);
  const muteUndoButton = useRef<HTMLButtonElement>(null);
  const followingHeading = useRef<HTMLHeadingElement>(null);
  const needsYouHeading = useRef<HTMLHeadingElement>(null);
  const focusRequest = useRef<FocusRequest | null>(null);
  const busyRef = useRef(new Set<string>());
  const ownChanges = useRef(new Map<string, number>());
  const undoTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const itemsRef = useRef(main.items);
  const doneElsewhereRef = useRef(doneElsewhere);
  useEffect(() => {
    itemsRef.current = main.items;
    doneElsewhereRef.current = doneElsewhere;
  });

  const registerLine = useCallback(
    (cuid: string) => (element: HTMLLIElement | null) => {
      if (element) {
        lineElements.current.set(cuid, element);
      } else {
        lineElements.current.delete(cuid);
      }
    },
    [],
  );
  const registerLink = useCallback(
    (cuid: string) => (element: HTMLAnchorElement | null) => {
      if (element) {
        linkElements.current.set(cuid, element);
      } else {
        linkElements.current.delete(cuid);
      }
    },
    [],
  );

  // After a line left or a page was added, the focus goes where it was asked (a moved list item loses it in Chrome)
  useEffect(() => {
    const request = focusRequest.current;
    if (!request) {
      return;
    }
    focusRequest.current = null;
    if (request === 'heading') {
      focusHeading();
    } else if (request === 'following-heading') {
      followingHeading.current?.focus();
    } else if (request === 'needs-you-heading') {
      needsYouHeading.current?.focus();
    } else {
      linkElements.current.get(request.cuid)?.focus();
    }
  }, [main.items, older.items, following.items, focusHeading]);

  // On an error the focus goes to "Retry"
  useEffect(() => {
    if (main.status === 'error') {
      retryButton.current?.focus();
    }
  }, [main.status]);

  // Seen: a line that stayed in the open panel for one second. One request for the lines of the same moment.
  const seenSent = useRef(new Set<string>());
  const unseenKey = [...main.items, ...following.items]
    .filter((item) => item.seen_at === null)
    .map((item) => item.cuid)
    .join(',');
  useEffect(() => {
    if (main.status !== 'ready' || unseenKey === '') {
      return;
    }
    const timers = new Map<string, ReturnType<typeof setTimeout>>();
    const ripe = new Set<string>();
    let flush: ReturnType<typeof setTimeout> | null = null;

    const send = async () => {
      flush = null;
      const cuids = [...ripe].filter((cuid) => !seenSent.current.has(cuid));
      ripe.clear();
      if (cuids.length === 0 || document.visibilityState === 'hidden') {
        return;
      }
      cuids.forEach((cuid) => seenSent.current.add(cuid));
      const result = await knowiiApiClient.notifications.seen(cuids);
      if (result.ok) {
        refreshCount();
      } else {
        cuids.forEach((cuid) => seenSent.current.delete(cuid));
      }
    };
    const start = (cuid: string) => {
      if (timers.has(cuid)) {
        return;
      }
      timers.set(
        cuid,
        setTimeout(() => {
          timers.delete(cuid);
          ripe.add(cuid);
          flush ??= setTimeout(() => void send(), 0);
        }, SEEN_AFTER_MS),
      );
    };
    const stop = (cuid: string) => {
      const timer = timers.get(cuid);
      if (timer) {
        clearTimeout(timer);
        timers.delete(cuid);
      }
    };

    const cuids = unseenKey.split(',');
    let observer: IntersectionObserver | null = null;
    if (typeof IntersectionObserver === 'undefined') {
      // No way to measure: every line of the open panel counts as visible
      cuids.forEach(start);
    } else {
      observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            const cuid = entry.target.getAttribute('data-notification-cuid');
            if (cuid) {
              if (entry.isIntersecting) {
                start(cuid);
              } else {
                stop(cuid);
              }
            }
          });
        },
        { threshold: 0.5 },
      );
      cuids.forEach((cuid) => {
        const element = lineElements.current.get(cuid);
        if (element) {
          observer?.observe(element);
        }
      });
    }

    return () => {
      observer?.disconnect();
      timers.forEach((timer) => clearTimeout(timer));
      if (flush) {
        clearTimeout(flush);
      }
    };
  }, [unseenKey, main.status, refreshCount]);

  // The server changed something (an event, a reconnection): lines that arrived wait behind "N new. Show"; a line that
  // left (done on another device, deleted) goes, unless the focus is on it
  const applyChange = useCallback(
    async (_change: NotificationsChange) => {
      const shown = itemsRef.current;
      const limit = Math.min(100, Math.max(NOTIFICATIONS_PER_PAGE, shown.length));
      const result = await knowiiApiClient.notifications.list({ limit });
      if (!result.ok) {
        return;
      }
      const now = Date.now();
      const isOwn = (cuid: string) => now - (ownChanges.current.get(cuid) ?? -Infinity) < OWN_CHANGE_MS;
      const current = new Map(itemsRef.current.map((item) => [item.cuid, item]));
      const fresh = result.data.items;
      const added = fresh.filter((item) => {
        const known = current.get(item.cuid);
        return !isOwn(item.cuid) && (!known || known.updated_at.getTime() < item.updated_at.getTime());
      });
      setIncoming(added.length > 0 ? { items: added, fresh, nextOffset: result.data.pagination.nextOffset } : null);

      const kept = new Set(fresh.map((item) => item.cuid));
      const left = itemsRef.current.filter((item) => !kept.has(item.cuid) && !isOwn(item.cuid));
      const toRemove: string[] = [];
      const toKeep: string[] = [];
      left.forEach((item) => {
        const element = lineElements.current.get(item.cuid);
        (element?.contains(document.activeElement) ? toKeep : toRemove).push(item.cuid);
      });
      if (toKeep.length > 0) {
        setDoneElsewhere((currentSet) => new Set([...currentSet, ...toKeep]));
      }
      toRemove.forEach((cuid) => main.remove(cuid));
    },
    [main],
  );
  const applyChangeRef = useRef(applyChange);
  useEffect(() => {
    applyChangeRef.current = applyChange;
  });
  useEffect(() => {
    if (main.status !== 'ready') {
      return;
    }
    return subscribe((change) => void applyChangeRef.current(change));
  }, [main.status, subscribe]);

  const showIncoming = () => {
    if (!incoming) {
      return;
    }
    const first = incoming.fresh[0];
    main.apply(incoming.fresh, incoming.nextOffset);
    setDoneElsewhere(new Set());
    setMessage(`${incoming.items.length} new shown`);
    setIncoming(null);
    if (first) {
      focusRequest.current = { cuid: first.cuid };
    }
  };

  const clearUndoTimer = () => {
    if (undoTimer.current) {
      clearTimeout(undoTimer.current);
      undoTimer.current = null;
    }
  };
  useEffect(() => clearUndoTimer, []);

  const ownChange = (cuid: string) => markOwnChange(ownChanges.current, cuid);

  const done = async (notification: Notification, feed: typeof main, isOlder: boolean) => {
    if (busyRef.current.has(notification.cuid)) {
      return;
    }
    busyRef.current.add(notification.cuid);
    setBusy(new Set(busyRef.current));
    setActionError(null);
    ownChange(notification.cuid);
    const result = await knowiiApiClient.notifications.read(notification.cuid);
    busyRef.current.delete(notification.cuid);
    setBusy(new Set(busyRef.current));
    if (!result.ok) {
      setActionError('We could not mark it as done. Try again.');
      return;
    }
    // The focus goes to the next line, else the line before, else the heading
    const list = feed.items;
    const index = list.findIndex((item) => item.cuid === notification.cuid);
    const neighbour = list[index + 1] ?? list[index - 1];
    focusRequest.current = neighbour ? { cuid: neighbour.cuid } : 'heading';
    feed.remove(notification.cuid);
    refreshCount();

    clearUndoTimer();
    setMessage(null);
    setMuted(null);
    setUndo({ notification, index: Math.max(index, 0), older: isOlder });
    undoTimer.current = setTimeout(() => {
      undoTimer.current = null;
      if (document.activeElement === undoButton.current) {
        focusHeading();
      }
      setUndo(null);
    }, UNDO_MS);
  };

  const undoDone = async () => {
    if (!undo || busyRef.current.has(`undo:${undo.notification.cuid}`)) {
      return;
    }
    const target = undo;
    busyRef.current.add(`undo:${target.notification.cuid}`);
    ownChange(target.notification.cuid);
    const result = await knowiiApiClient.notifications.unread(target.notification.cuid);
    busyRef.current.delete(`undo:${target.notification.cuid}`);
    clearUndoTimer();
    setUndo(null);
    if (result.ok) {
      (target.older ? older : main).insert(result.data, target.index);
      focusRequest.current = { cuid: result.data.cuid };
      refreshCount();
      return;
    }
    // A newer notification about the same thing exists (422): the list is out of date, so load it again
    focusHeading();
    if (result.status === 422) {
      setMessage('A newer notification about this exists. The list was updated.');
      void main.load();
    } else {
      setActionError('We could not undo. Try again.');
    }
  };

  const openLine = (event: MouseEvent, notification: Notification, href: string) => {
    void knowiiApiClient.notifications.read(notification.cuid);
    ownChange(notification.cuid);
    const target = new URL(href, window.location.href);
    // A modifier key or a middle click opens a new tab: the panel stays
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
      return;
    }
    onClose();
    if (target.pathname === window.location.pathname) {
      // The same page: the page looks at the address (comments, chat), so only the address changes
      event.preventDefault();
      setTimeout(() => {
        window.history.pushState(null, '', `${target.pathname}${target.search}${target.hash}`);
        window.dispatchEvent(new HashChangeEvent('hashchange'));
      }, SAME_PAGE_JUMP_DELAY_MS);
    }
  };

  const leaveLine = (notification: Notification) => {
    setDoneElsewhere((current) => {
      const next = new Set(current);
      next.delete(notification.cuid);
      return next;
    });
    main.remove(notification.cuid);
  };

  // A line left by a button: the focus goes to the next line, else the line before, else the heading of its list
  const moveFocusFrom = (feed: typeof main, cuid: string, fallback: FocusRequest, gone: ReadonlySet<string> = new Set([cuid])) => {
    const list = feed.items;
    const index = list.findIndex((item) => item.cuid === cuid);
    const after = list.slice(index + 1).find((item) => !gone.has(item.cuid));
    const before = list
      .slice(0, Math.max(index, 0))
      .reverse()
      .find((item) => !gone.has(item.cuid));
    const neighbour = after ?? before;
    focusRequest.current = neighbour ? { cuid: neighbour.cuid } : fallback;
  };

  const showMuted = (undoMute: () => Promise<boolean>) => {
    clearUndoTimer();
    setMessage(null);
    setUndo(null);
    setMuted({ undo: undoMute });
    undoTimer.current = setTimeout(() => {
      undoTimer.current = null;
      if (document.activeElement === muteUndoButton.current) {
        focusHeading();
      }
      setMuted(null);
    }, UNDO_MS);
  };

  const undoMute = async () => {
    if (!muted || busyRef.current.has('undo:mute')) {
      return;
    }
    const target = muted;
    busyRef.current.add('undo:mute');
    const ok = await target.undo();
    busyRef.current.delete('undo:mute');
    clearUndoTimer();
    setMuted(null);
    focusHeading();
    if (ok) {
      setMessage('Mute undone');
    } else {
      setActionError('We could not undo. Try again.');
    }
  };

  // "Stop following" on a thread line: the thread stops, the line leaves, the row is read
  const stopFollowing = async (notification: Notification, feed: typeof main) => {
    const thread = threadOf(notification);
    if (!thread || busyRef.current.has(notification.cuid) || !notification.space) {
      return;
    }
    busyRef.current.add(notification.cuid);
    setBusy(new Set(busyRef.current));
    setActionError(null);
    ownChange(notification.cuid);
    const { community, space } = notification;
    const result =
      thread.kind === 'post'
        ? await knowiiApiClient.threads.stopPost(community.cuid, space.cuid, thread.postCuid)
        : await knowiiApiClient.threads.stopMessage(community.cuid, space.cuid, thread.messageCuid);
    busyRef.current.delete(notification.cuid);
    setBusy(new Set(busyRef.current));
    if (!result.ok) {
      setActionError('We could not stop following. Try again.');
      return;
    }
    void knowiiApiClient.notifications.read(notification.cuid);
    moveFocusFrom(feed, notification.cuid, 'following-heading');
    feed.remove(notification.cuid);
    setMessage('You stopped following this thread');
  };

  const sameThread = (a: Notification, b: Notification) => {
    const left = threadOf(a);
    const right = threadOf(b);
    return (
      left !== null &&
      right !== null &&
      a.space?.cuid === b.space?.cuid &&
      ((left.kind === 'post' && right.kind === 'post' && left.postCuid === right.postCuid) ||
        (left.kind === 'message' && right.kind === 'message' && left.messageCuid === right.messageCuid))
    );
  };

  // Lines that left because of a mute: every feed drops them. The focus goes where the line of the menu was.
  const dropLines = (feed: typeof main, notification: Notification, gone: (other: Notification) => boolean) => {
    const ids = new Set([...main.items, ...older.items, ...following.items].filter(gone).map((item) => item.cuid));
    moveFocusFrom(feed, notification.cuid, feed === following ? 'following-heading' : 'heading', ids);
    [main, older, following].forEach((list) => list.setItems((current) => current.filter((item) => !ids.has(item.cuid))));
    return ids;
  };

  // "Mute this thread": nothing from the thread, a mention included. It applies at once and can be undone for 8 seconds.
  const muteThread = async (notification: Notification, feed: typeof main) => {
    const thread = threadOf(notification);
    if (!thread || !notification.space || busyRef.current.has(notification.cuid)) {
      return;
    }
    busyRef.current.add(notification.cuid);
    setBusy(new Set(busyRef.current));
    setActionError(null);
    const { community, space } = notification;
    const before =
      thread.kind === 'post'
        ? await knowiiApiClient.threads.getPost(community.cuid, space.cuid, thread.postCuid)
        : await knowiiApiClient.threads.getMessage(community.cuid, space.cuid, thread.messageCuid);
    const result =
      thread.kind === 'post'
        ? await knowiiApiClient.threads.mutePost(community.cuid, space.cuid, thread.postCuid)
        : await knowiiApiClient.threads.muteMessage(community.cuid, space.cuid, thread.messageCuid);
    busyRef.current.delete(notification.cuid);
    setBusy(new Set(busyRef.current));
    if (!result.ok) {
      setActionError('We could not mute this thread. Try again.');
      return;
    }
    const ids = dropLines(feed, notification, (other) => sameThread(notification, other));
    ids.forEach(ownChange);
    refreshCount();
    const was = before.ok ? before.data.state : null;
    showMuted(async () => {
      const back =
        thread.kind === 'post'
          ? await (was === 'following'
              ? knowiiApiClient.threads.followPost(community.cuid, space.cuid, thread.postCuid)
              : knowiiApiClient.threads.stopPost(community.cuid, space.cuid, thread.postCuid))
          : await (was === 'following'
              ? knowiiApiClient.threads.followMessage(community.cuid, space.cuid, thread.messageCuid)
              : knowiiApiClient.threads.stopMessage(community.cuid, space.cuid, thread.messageCuid));
      return back.ok;
    });
  };

  // "Mute <space>": nothing from the space, a mention included. The open lines of the space go.
  const muteSpace = async (notification: Notification, feed: typeof main) => {
    const space = notification.space;
    const busyKey = `space:${space?.cuid ?? ''}`;
    if (!space || busyRef.current.has(busyKey)) {
      return;
    }
    busyRef.current.add(busyKey);
    setBusy(new Set(busyRef.current));
    setActionError(null);
    const communityCuid = notification.community.cuid;
    const before = await knowiiApiClient.notificationLevels.get(communityCuid, space.cuid);
    const result = await knowiiApiClient.notificationLevels.set(communityCuid, space.cuid, 'none');
    busyRef.current.delete(busyKey);
    setBusy(new Set(busyRef.current));
    if (!result.ok) {
      setActionError(`We could not mute ${space.name}. Try again.`);
      return;
    }
    const ids = dropLines(feed, notification, (other) => other.space?.cuid === space.cuid);
    ids.forEach(ownChange);
    refreshCount();
    showMuted(async () => {
      const back =
        before.ok && before.data.chosen
          ? await knowiiApiClient.notificationLevels.set(communityCuid, space.cuid, before.data.level)
          : await knowiiApiClient.notificationLevels.reset(communityCuid, space.cuid);
      return back.ok;
    });
  };

  // "I am caught up": one request clears every line of "Following", never a line of "Needs you"
  const caughtUp = async () => {
    if (busyRef.current.has('caught-up')) {
      return;
    }
    busyRef.current.add('caught-up');
    setBusy(new Set(busyRef.current));
    setActionError(null);
    const result = await knowiiApiClient.notifications.caughtUp();
    busyRef.current.delete('caught-up');
    setBusy(new Set(busyRef.current));
    if (!result.ok) {
      setActionError('We could not clear Following. Try again.');
      return;
    }
    // The server forgot the date of the "Catch up" card with the lines: the card goes everywhere (#1476)
    if (catchUpSince) {
      clearCatchUp(catchUpSince);
    }
    following.items.forEach((item) => ownChange(item.cuid));
    focusRequest.current = 'following-heading';
    following.setItems([]);
    clearUndoTimer();
    setUndo(null);
    setMuted(null);
    setMessage('You are caught up');
  };

  const loadMore = async (feed: typeof main) => {
    const added = await feed.loadMore((page) => {
      const first = page[0];
      if (first) {
        focusRequest.current = { cuid: first.cuid };
      }
    });
    setMessage(`${added.length} more loaded`);
  };

  const renderLine = (feed: typeof main, notification: Notification, isOlder: boolean): ReactNode => (
    <NotificationLine
      key={notification.cuid}
      notification={notification}
      href={notificationHref(notification, route)}
      isNew={!isOlder && feed.freshCuids.has(notification.cuid)}
      showCommunity={showCommunity}
      busy={busy.has(notification.cuid) || busy.has(`space:${notification.space?.cuid ?? ''}`)}
      doneElsewhere={doneElsewhere.has(notification.cuid)}
      settingsHref={`${route(USER_NOTIFICATIONS_URL, {})}#notifications-space-${notification.space?.cuid ?? ''}`}
      onOpen={openLine}
      onDone={(n) => void done(n, feed, isOlder)}
      onStopFollowing={(n) => void stopFollowing(n, feed)}
      onMuteThread={(n) => void muteThread(n, feed)}
      onMuteSpace={(n) => void muteSpace(n, feed)}
      onLeave={leaveLine}
      registerLine={registerLine(notification.cuid)}
      registerLink={registerLink(notification.cuid)}
    />
  );

  const lines = main.items;

  return (
    <div className="flex flex-col gap-3">
      {/* The status text of the panel: "Done. Undo", "20 more loaded" (polite) */}
      <div role="status" aria-live="polite" className={undo || muted || message ? 'rounded-lg bg-overlay/10 px-3 py-2 text-sm' : undefined}>
        {undo ? (
          <>
            Done.{' '}
            <button
              ref={undoButton}
              type="button"
              onClick={() => void undoDone()}
              className="cursor-pointer rounded font-bold underline focus-visible:outline-2 focus-visible:outline-primary"
            >
              Undo
            </button>
          </>
        ) : muted ? (
          <>
            Muted.{' '}
            <button
              ref={muteUndoButton}
              type="button"
              onClick={() => void undoMute()}
              className="cursor-pointer rounded font-bold underline focus-visible:outline-2 focus-visible:outline-primary"
            >
              Undo
            </button>
          </>
        ) : (
          message
        )}
      </div>
      {actionError && (
        <p role="alert" className="rounded-lg border border-danger px-3 py-2 text-sm text-danger">
          {actionError}
        </p>
      )}

      {/* "Catch up" (#1476, design 5.5): above the lists, for the community of the page (or every community on the page) */}
      {catchUpCommunities.map((community) => (
        <CatchUpCard
          key={community.cuid}
          community={community}
          headingLevel={variant === 'page' ? 2 : 3}
          showCommunity={catchUpCommunities.length > 1}
          onShowAll={() => needsYouHeading.current?.focus()}
          onCaughtUp={() => {
            following.items.forEach((item) => ownChange(item.cuid));
            following.setItems([]);
            clearUndoTimer();
            setUndo(null);
            setMuted(null);
            needsYouHeading.current?.focus();
          }}
        />
      ))}

      <section aria-labelledby="notifications-needs-you" className="flex flex-col gap-2">
        <Heading
          id="notifications-needs-you"
          ref={needsYouHeading}
          tabIndex={-1}
          className="rounded text-sm font-extrabold outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        >
          Needs you
        </Heading>

        {incoming && (
          <Button variant="secondary" size="sm" onClick={showIncoming} className="self-start">
            {incoming.items.length} new. Show
          </Button>
        )}

        {main.status === 'loading' && (
          <ul aria-busy="true" className="flex flex-col gap-2">
            {Array.from({ length: SKELETON_LINES }, (_, index) => (
              <li key={index} aria-hidden="true" className="flex gap-3 px-2 py-3">
                <span className="size-8 shrink-0 rounded-full bg-overlay/10 motion-safe:animate-pulse" />
                <span className="flex grow flex-col gap-2">
                  <span className="h-3 w-3/4 rounded bg-overlay/10 motion-safe:animate-pulse" />
                  <span className="h-3 w-full rounded bg-overlay/10 motion-safe:animate-pulse" />
                </span>
              </li>
            ))}
          </ul>
        )}

        {main.status === 'error' && (
          <div className="flex flex-col items-start gap-2 rounded-lg border border-border px-3 py-3 text-sm">
            <p>We could not load your notifications.</p>
            <Button ref={retryButton} variant="secondary" size="sm" onClick={main.retry}>
              Retry
            </Button>
          </div>
        )}

        {main.status === 'ready' &&
          (lines.length === 0 ? (
            <EmptyState title="Nothing needs you." description="Everything is quiet. That is fine." className="py-6" />
          ) : (
            <ul aria-busy={main.loadingMore || undefined} className="flex flex-col">
              {lines.map((notification) => renderLine(main, notification, false))}
            </ul>
          ))}

        {main.status === 'ready' && main.hasMore && (
          <Button
            variant="secondary"
            size="sm"
            aria-busy={main.loadingMore || undefined}
            aria-disabled={main.loadingMore || undefined}
            onClick={() => void loadMore(main)}
            className="self-start"
          >
            Load older
          </Button>
        )}
      </section>

      {/* "Following": level 2, the last 14 days. Never counted by the bell, never announced (design 5.1, 9.3) */}
      <section aria-labelledby="notifications-following" className="flex flex-col gap-2 border-t border-border pt-3">
        <div className="flex items-center justify-between gap-2">
          <Heading
            id="notifications-following"
            ref={followingHeading}
            tabIndex={-1}
            className="rounded text-sm font-extrabold outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
          >
            Following
          </Heading>
          {following.status === 'ready' && following.items.length > 0 && (
            <Button
              variant="secondary"
              size="sm"
              aria-disabled={busy.has('caught-up') || undefined}
              aria-busy={busy.has('caught-up') || undefined}
              onClick={() => void caughtUp()}
            >
              I am caught up
            </Button>
          )}
        </div>

        {following.status === 'loading' && (
          <div aria-busy="true" className="flex flex-col gap-2">
            {Array.from({ length: FOLLOWING_SKELETON_LINES }, (_, index) => (
              <div key={index} aria-hidden="true" className="flex gap-3 px-2 py-3">
                <span className="size-8 shrink-0 rounded-full bg-overlay/10 motion-safe:animate-pulse" />
                <span className="flex grow flex-col gap-2">
                  <span className="h-3 w-3/4 rounded bg-overlay/10 motion-safe:animate-pulse" />
                </span>
              </div>
            ))}
          </div>
        )}

        {following.status === 'error' && (
          <div className="flex flex-col items-start gap-2 rounded-lg border border-border px-3 py-3 text-sm">
            <p>We could not load Following.</p>
            <Button variant="secondary" size="sm" onClick={following.retry}>
              Retry Following
            </Button>
          </div>
        )}

        {following.status === 'ready' &&
          (following.items.length === 0 ? (
            <p className="px-2 text-sm text-muted">Nothing new in what you follow.</p>
          ) : (
            <ul aria-busy={following.loadingMore || undefined} className="flex flex-col">
              {following.items.map((notification) => renderLine(following, notification, false))}
            </ul>
          ))}

        {following.status === 'ready' && following.hasMore && (
          <Button
            variant="secondary"
            size="sm"
            aria-label="Load more in Following"
            aria-busy={following.loadingMore || undefined}
            aria-disabled={following.loadingMore || undefined}
            onClick={() => void loadMore(following)}
            className="self-start"
          >
            Load more
          </Button>
        )}
      </section>

      {/* "Earlier": more than 14 days, never counted. Loaded when the member opens it the first time. */}
      <section className="flex flex-col gap-2 border-t border-border pt-3">
        <Heading className="text-sm font-extrabold">
          <button
            type="button"
            aria-expanded={olderOpen}
            aria-controls="notifications-earlier"
            onClick={() => setOlderOpen((open) => !open)}
            className="flex cursor-pointer items-center gap-2 rounded focus-visible:outline-2 focus-visible:outline-primary"
          >
            {olderOpen ? <FaChevronDown aria-hidden="true" className="size-3" /> : <FaChevronRight aria-hidden="true" className="size-3" />}
            Earlier
          </button>
        </Heading>
        <div id="notifications-earlier" hidden={!olderOpen}>
          {olderOpen && older.status === 'loading' && <p className="px-2 text-sm text-muted">Loading earlier notifications.</p>}
          {olderOpen && older.status === 'error' && (
            <div className="flex flex-col items-start gap-2 px-2 text-sm">
              <p>We could not load the earlier notifications.</p>
              <Button variant="secondary" size="sm" onClick={older.retry}>
                Retry
              </Button>
            </div>
          )}
          {olderOpen && older.status === 'ready' && older.items.length === 0 && (
            <p className="px-2 text-sm text-muted">No earlier notifications.</p>
          )}
          {olderOpen && older.status === 'ready' && older.items.length > 0 && (
            <ul aria-busy={older.loadingMore || undefined} className="flex flex-col">
              {older.items.map((notification) => renderLine(older, notification, true))}
            </ul>
          )}
          {olderOpen && older.status === 'ready' && older.hasMore && (
            <Button
              variant="secondary"
              size="sm"
              aria-busy={older.loadingMore || undefined}
              aria-disabled={older.loadingMore || undefined}
              onClick={() => void loadMore(older)}
              className="mt-2 ml-2"
            >
              Load more
            </Button>
          )}
        </div>
      </section>

      {variant === 'panel' && (
        <Link
          href={route(NOTIFICATIONS_URL, {})}
          onClick={onClose}
          className="self-start rounded text-sm font-bold underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-primary"
        >
          Open the Notifications page
        </Link>
      )}
    </div>
  );
}
