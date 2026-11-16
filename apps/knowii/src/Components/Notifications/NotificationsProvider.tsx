import type { ReactNode } from 'react';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import type { Notification } from '@knowii/common';
import { knowiiApiClient, NOTIFICATIONS_URL, notificationSentence, useSocket, useSocketReconnected, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import { NotificationPopup } from './NotificationPopup';
import { notificationHref } from './notification-link';
import { isOpenThread } from './notification-open-thread';

/**
 * What the screens do after an event of the server: the cuids of the rows that changed, and whether more rows than one
 * changed (a list marked as seen, rows deleted, a reconnection)
 */
export interface NotificationsChange {
  cuids: string[];
  many: boolean;
}

interface NotificationsContextValue {
  /**
   * The number of the bell: always the value of the server, never computed here (design 5.2.6)
   */
  count: number;
  /**
   * The conversations with new messages, in every community (#1476): the tab title adds it to the bell number
   */
  messages: number;
  /**
   * Open the Notifications panel of the bell (the "Show all" link of the "Catch up" card). Nothing happens without a bell.
   */
  openPanel: () => void;
  /**
   * The bell tells the provider how to open it, and when it is open: no pop-up while the panel is open (#1476, design 5.3)
   */
  registerPanel: (opener: (() => void) | null) => void;
  setPanelOpen: (open: boolean) => void;
  /**
   * Load the number again, at once (after the panel marked lines as seen)
   */
  refreshCount: () => void;
  /**
   * Called after each burst of events, and after a reconnection. Returns the function that stops listening.
   */
  subscribe: (listener: (change: NotificationsChange) => void) => () => void;
}

interface PopupState {
  id: number;
  lines: Notification[];
  text: string;
  href: string | null;
}

const NotificationsContext = createContext<NotificationsContextValue | null>(null);

// Several events in a short time give one request
export const NOTIFICATION_DEBOUNCE_MS = 300;
// The live region says something once per 30 seconds at most (design 9.3.1)
export const NOTIFICATION_ANNOUNCE_WINDOW_MS = 30_000;
// The lines that an event may have added: the newest ones of the list
const ANNOUNCE_LOOKUP = 5;

/**
 * The same, for the parts that may render without a provider (a visitor has none): null then
 */
export function useNotificationsOptional(): NotificationsContextValue | null {
  return useContext(NotificationsContext);
}

// A new level 1 line may show a pop-up: a second one within this time replaces the first with "<N> new for you" (design 5.3)
export const NOTIFICATION_POPUP_WINDOW_MS = 30_000;

export function useNotifications(): NotificationsContextValue {
  const value = useContext(NotificationsContext);
  if (!value) {
    throw new Error('useNotifications needs a <NotificationsProvider>');
  }
  return value;
}

/**
 * The source of truth of the bell number and the one polite live region of the layout (#1484, design 5.2, 6.12, 9.3).
 * For a signed-in member only: a visitor has no provider, so no request, no socket and no live region.
 *
 * The number is loaded on page load, when the window gets the focus, when the socket reconnects and after each
 * `notifications.changed` event of the channel `user.{cuid}`. It is never incremented here.
 */
export function NotificationsProvider({ userCuid, children }: { userCuid: string; children: ReactNode }) {
  const route = useRoute();
  const page = useTypedPage();
  // The switch "Show pop-ups" of the account: on unless the member turned it off
  const popupsOn = page.props.auth?.user?.notification_popups !== false;
  const [count, setCount] = useState(0);
  const [messages, setMessages] = useState(0);
  const [message, setMessage] = useState<{ id: number; text: string } | null>(null);
  const [popup, setPopup] = useState<PopupState | null>(null);
  const popupRef = useRef<PopupState | null>(null);
  const popupId = useRef(0);
  const popupWindow = useRef<{ startedAt: number; total: number; lines: Notification[] } | null>(null);
  const panelOpen = useRef(false);
  const panelOpener = useRef<(() => void) | null>(null);
  const popupsOnRef = useRef(popupsOn);
  const routeRef = useRef(route);
  useEffect(() => {
    popupsOnRef.current = popupsOn;
    routeRef.current = route;
  });

  const listeners = useRef(new Set<(change: NotificationsChange) => void>());
  // The answer of an older request never replaces the answer of a newer one
  const requestId = useRef(0);
  const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pending = useRef<{ cuids: Set<string>; many: boolean }>({ cuids: new Set(), many: false });
  // The live region: when it spoke last, what waits for the end of the window, and what it already announced
  const lastAnnouncement = useRef<number | null>(null);
  const waiting = useRef<Notification[]>([]);
  const announceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const announced = useRef(new Map<string, number>());
  const messageId = useRef(0);

  const loadCount = useCallback(async () => {
    const id = ++requestId.current;
    const result = await knowiiApiClient.notifications.count();
    if (result.ok && id === requestId.current) {
      setCount(result.data.count);
      setMessages(result.data.messages);
    }
  }, []);

  const say = useCallback((items: Notification[]) => {
    const first = items[0];
    const text = items.length === 1 && first ? notificationSentence(first) : `${items.length} new for you`;
    messageId.current += 1;
    setMessage({ id: messageId.current, text });
  }, []);

  const announce = useCallback(
    (items: Notification[]) => {
      if (items.length === 0) {
        return;
      }
      const now = Date.now();
      const wait = lastAnnouncement.current === null ? 0 : lastAnnouncement.current + NOTIFICATION_ANNOUNCE_WINDOW_MS - now;
      if (wait <= 0 && announceTimer.current === null) {
        lastAnnouncement.current = now;
        say(items);
        return;
      }
      // Inside the window: they wait, and become one message ("3 new for you")
      waiting.current.push(...items);
      announceTimer.current ??= setTimeout(
        () => {
          const batch = waiting.current;
          waiting.current = [];
          announceTimer.current = null;
          lastAnnouncement.current = Date.now();
          say(batch);
        },
        Math.max(wait, 0),
      );
    },
    [say],
  );

  // The pop-up (design 5.3, 9.4): only for new level 1 lines, only when the tab is visible, never for the thread that is open
  // on the page, never while the panel is open. Its text is not announced again: the live region already said it.
  const showPopup = useCallback((items: Notification[]) => {
    if (!popupsOnRef.current || panelOpen.current || document.visibilityState !== 'visible') {
      return;
    }
    const candidates = items.filter((item) => item.level === 1 && !isOpenThread(notificationHref(item, routeRef.current)));
    const first = candidates[0];
    if (!first) {
      return;
    }
    const now = Date.now();
    popupId.current += 1;
    const id = popupId.current;
    const recent = popupWindow.current;
    const listHref = routeRef.current(NOTIFICATIONS_URL, {});
    let next: PopupState;
    if (recent && now - recent.startedAt < NOTIFICATION_POPUP_WINDOW_MS) {
      // A second line within 30 seconds of the first one: one pop-up for all of them, also when the first pop-up already
      // closed (by its timer or by the member). The live region counts the same way.
      const total = recent.total + candidates.length;
      const lines = [...recent.lines, ...candidates];
      popupWindow.current = { startedAt: recent.startedAt, total, lines };
      next = { id, lines, text: `${total} new for you`, href: listHref };
    } else {
      popupWindow.current = { startedAt: now, total: candidates.length, lines: candidates };
      next = {
        id,
        lines: candidates,
        text: candidates.length === 1 ? notificationSentence(first) : `${candidates.length} new for you`,
        href: candidates.length === 1 ? notificationHref(first, routeRef.current) : listHref,
      };
    }
    popupRef.current = next;
    setPopup(next);
  }, []);

  // A new level 1 row that the member did not see: say who and what, once (the count is never announced)
  const announceArrivals = useCallback(
    async (cuids: string[]) => {
      const result = await knowiiApiClient.notifications.list({ limit: ANNOUNCE_LOOKUP });
      if (!result.ok) {
        return;
      }
      const fresh = result.data.items.filter((item) => {
        const updatedAt = item.updated_at.getTime();
        const isNew = cuids.includes(item.cuid) && item.seen_at === null && item.read_at === null && item.level === 1;
        const known = announced.current.get(item.cuid);
        if (!isNew || (known !== undefined && known >= updatedAt)) {
          return false;
        }
        announced.current.set(item.cuid, updatedAt);
        return true;
      });
      announce(fresh);
      showPopup(fresh);
    },
    [announce, showPopup],
  );

  const notify = useCallback(
    (change: NotificationsChange) => {
      listeners.current.forEach((listener) => listener(change));
      if (change.cuids.length > 0) {
        void announceArrivals(change.cuids);
      }
    },
    [announceArrivals],
  );

  const schedule = useCallback(
    (cuid: string | null) => {
      if (cuid === null) {
        pending.current.many = true;
      } else {
        pending.current.cuids.add(cuid);
      }
      debounce.current ??= setTimeout(() => {
        debounce.current = null;
        const change = { cuids: [...pending.current.cuids], many: pending.current.many };
        pending.current = { cuids: new Set(), many: false };
        void loadCount();
        notify(change);
      }, NOTIFICATION_DEBOUNCE_MS);
    },
    [loadCount, notify],
  );

  // Page load
  useEffect(() => {
    void loadCount();
  }, [loadCount]);

  // The window gets the focus: events may have been missed while it was in the background
  useEffect(() => {
    const onFocus = () => {
      void loadCount();
    };
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [loadCount]);

  // The socket comes back: load again, without a message (design 6.12.3)
  useSocketReconnected(() => {
    void loadCount();
    listeners.current.forEach((listener) => listener({ cuids: [], many: true }));
  });

  const userChannel = useMemo(() => ({ type: 'user' as const, userCuid }), [userCuid]);
  useSocket({
    channel: userChannel,
    event: 'notifications.changed',
    callback: (_event, payload) => schedule(payload.notification_cuid),
  });

  useEffect(
    () => () => {
      if (debounce.current) {
        clearTimeout(debounce.current);
      }
      if (announceTimer.current) {
        clearTimeout(announceTimer.current);
      }
    },
    [],
  );

  // Stable functions: the panel depends on them, and it must not restart its timers at each new count
  const refreshCount = useCallback(() => {
    void loadCount();
  }, [loadCount]);
  const subscribe = useCallback((listener: (change: NotificationsChange) => void) => {
    listeners.current.add(listener);
    return () => {
      listeners.current.delete(listener);
    };
  }, []);

  // The 30 seconds of the window go on after the pop-up closed: a line that comes in that time is "<N> new for you"
  const closePopup = useCallback(() => {
    popupRef.current = null;
    setPopup(null);
  }, []);
  const registerPanel = useCallback((opener: (() => void) | null) => {
    panelOpener.current = opener;
  }, []);
  const openPanel = useCallback(() => {
    panelOpener.current?.();
  }, []);
  const setPanelOpen = useCallback(
    (open: boolean) => {
      panelOpen.current = open;
      if (open) {
        // The member looks at the list: the next line starts a new window
        popupWindow.current = null;
        closePopup();
      }
    },
    [closePopup],
  );

  const value = useMemo<NotificationsContextValue>(
    () => ({ count, messages, openPanel, registerPanel, setPanelOpen, refreshCount, subscribe }),
    [count, messages, openPanel, registerPanel, setPanelOpen, refreshCount, subscribe],
  );

  return (
    <NotificationsContext.Provider value={value}>
      {children}
      {popup && popupsOn && (
        <NotificationPopup
          key={popup.id}
          text={popup.text}
          href={popup.href}
          onOpen={() => {
            // A single line that the member opens is read, like a line of the panel
            const only = popup.lines.length === 1 ? popup.lines[0] : undefined;
            if (only) {
              void knowiiApiClient.notifications.read(only.cuid);
            }
            closePopup();
          }}
          onClose={closePopup}
        />
      )}
      {/* The one polite region of the layout, there from the load. Only arrivals of level 1 go through it. */}
      <div role="status" aria-live="polite" aria-atomic="true" className="sr-only" data-testid="notification-live-region">
        {message && <span key={message.id}>{message.text}</span>}
      </div>
    </NotificationsContext.Provider>
  );
}
