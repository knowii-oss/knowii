import type { ReactNode } from 'react';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import type { Notification } from '@knowii/common';
import { knowiiApiClient, notificationSentence, useSocket, useSocketReconnected } from '@knowii/common';

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
   * Load the number again, at once (after the panel marked lines as seen)
   */
  refreshCount: () => void;
  /**
   * Called after each burst of events, and after a reconnection. Returns the function that stops listening.
   */
  subscribe: (listener: (change: NotificationsChange) => void) => () => void;
}

const NotificationsContext = createContext<NotificationsContextValue | null>(null);

// Several events in a short time give one request
export const NOTIFICATION_DEBOUNCE_MS = 300;
// The live region says something once per 30 seconds at most (design 9.3.1)
export const NOTIFICATION_ANNOUNCE_WINDOW_MS = 30_000;
// The lines that an event may have added: the newest ones of the list
const ANNOUNCE_LOOKUP = 5;

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
  const [count, setCount] = useState(0);
  const [message, setMessage] = useState<{ id: number; text: string } | null>(null);

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
    },
    [announce],
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

  const value = useMemo<NotificationsContextValue>(() => ({ count, refreshCount, subscribe }), [count, refreshCount, subscribe]);

  return (
    <NotificationsContext.Provider value={value}>
      {children}
      {/* The one polite region of the layout, there from the load. Only arrivals of level 1 go through it. */}
      <div role="status" aria-live="polite" aria-atomic="true" className="sr-only" data-testid="notification-live-region">
        {message && <span key={message.id}>{message.text}</span>}
      </div>
    </NotificationsContext.Provider>
  );
}
