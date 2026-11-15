import { useCallback, useEffect, useRef, useState } from 'react';
import type { Notification } from '@knowii/common';
import { knowiiApiClient, NOTIFICATIONS_PER_PAGE } from '@knowii/common';

export type FeedStatus = 'loading' | 'ready' | 'error';

/**
 * One list of the panel: the notifications of the last 14 days, or the older ones (#1484). It loads the first page, the
 * next pages with offsets, and gives the operations that the panel needs on the lines.
 */
export function useNotificationFeed({ older = false, enabled = true }: { older?: boolean; enabled?: boolean } = {}) {
  const [items, setItems] = useState<Notification[]>([]);
  const [status, setStatus] = useState<FeedStatus>('loading');
  const [nextOffset, setNextOffset] = useState<number | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);
  // The lines that were new when the list loaded: "New" stays while the panel is open, also after they are seen
  const [freshCuids, setFreshCuids] = useState<ReadonlySet<string>>(new Set());
  const requestId = useRef(0);
  const loadingMoreRef = useRef(false);

  const apply = useCallback((page: Notification[], next: number | null) => {
    setItems(page);
    setNextOffset(next);
    setFreshCuids(new Set(page.filter((item) => item.seen_at === null).map((item) => item.cuid)));
    setStatus('ready');
  }, []);

  /**
   * Load the first page. Returns the page, so that the caller can compare it with what is shown.
   */
  const load = useCallback(async (): Promise<Notification[] | null> => {
    const id = ++requestId.current;
    const result = await knowiiApiClient.notifications.list({ older });
    if (id !== requestId.current) {
      return null;
    }
    if (!result.ok) {
      setStatus('error');
      return null;
    }
    apply(result.data.items, result.data.pagination.nextOffset);
    return result.data.items;
  }, [apply, older]);

  const retry = useCallback(() => {
    setStatus('loading');
    void load();
  }, [load]);

  // The first page, when the list opens
  useEffect(() => {
    if (!enabled) {
      return;
    }
    const run = async () => {
      const id = ++requestId.current;
      const result = await knowiiApiClient.notifications.list({ older });
      if (id !== requestId.current) {
        return;
      }
      if (result.ok) {
        apply(result.data.items, result.data.pagination.nextOffset);
      } else {
        setStatus('error');
      }
    };
    void run();
    return () => {
      requestId.current += 1;
    };
  }, [enabled, older, apply]);

  /**
   * The next page. Returns the lines that were added, and calls `onAdded` with them before they render.
   */
  const loadMore = useCallback(
    async (onAdded?: (added: Notification[]) => void): Promise<Notification[]> => {
      if (nextOffset === null || loadingMoreRef.current) {
        return [];
      }
      loadingMoreRef.current = true;
      setLoadingMore(true);
      const result = await knowiiApiClient.notifications.list({ older, offset: nextOffset, limit: NOTIFICATIONS_PER_PAGE });
      loadingMoreRef.current = false;
      setLoadingMore(false);
      if (!result.ok) {
        return [];
      }
      const added = result.data.items.filter((item) => !items.some((shown) => shown.cuid === item.cuid));
      // Before the new lines render, so that the caller can ask for the focus on the first one
      onAdded?.(added);
      setItems([...items, ...added]);
      setNextOffset(result.data.pagination.nextOffset);
      setFreshCuids((current) => new Set([...current, ...added.filter((item) => item.seen_at === null).map((item) => item.cuid)]));
      return added;
    },
    [items, nextOffset, older],
  );

  const remove = useCallback((cuid: string) => {
    setItems((current) => current.filter((item) => item.cuid !== cuid));
  }, []);

  const insert = useCallback((notification: Notification, index: number) => {
    setItems((current) => {
      if (current.some((item) => item.cuid === notification.cuid)) {
        return current;
      }
      const next = [...current];
      next.splice(Math.min(index, next.length), 0, notification);
      return next;
    });
  }, []);

  return { items, status, hasMore: nextOffset !== null, loadingMore, freshCuids, load, retry, loadMore, remove, insert, apply, setItems };
}
