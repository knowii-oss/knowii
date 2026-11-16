import { THREAD_QUERY_PARAM } from './notification-link';

/**
 * Whether the address of an item is the thread or the page that is open now (#1476, design 5.3.1): the same page, and the
 * same thread of a chat space (`?thread=`, or none). Then the member already sees it: no pop-up.
 */
export function isOpenThread(href: string | null, current: Location = window.location): boolean {
  if (href === null) {
    return false;
  }
  try {
    const target = new URL(href, current.href);
    if (target.pathname !== current.pathname) {
      return false;
    }
    const here = new URLSearchParams(current.search).get(THREAD_QUERY_PARAM);
    return target.searchParams.get(THREAD_QUERY_PARAM) === here;
  } catch {
    return false;
  }
}
