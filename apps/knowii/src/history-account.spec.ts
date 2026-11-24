import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { http, router } from '@inertiajs/react';
import { accountOf, HISTORY_ACCOUNT_KEY, startHistoryOfOneAccount } from './history-account';

/*
 * The history of a tab keeps the pages of one account, with the real router of Inertia: only the network is replaced. When
 * a page arrives for another account or for no account, the tab clears the history before the page is shown.
 */

const ORIGIN = window.location.origin;
const ADA = 'tz4a98xxat96iws9zmbrgj3a';
const GRACE = 'pfh0haxfpzowht3oi213cqos';

function propsOf(account: string | null | undefined): Record<string, unknown> {
  if (account === undefined) {
    return {};
  }

  return { auth: { user: account === null ? null : { cuid: account, name: 'A member', email: 'member@example.com' } } };
}

function pageAt(url: string, props: Record<string, unknown>) {
  return { component: url, url, version: 'spec', props: { errors: {}, ...props }, flash: {}, rescuedProps: [], rememberedState: {} };
}

// The server of this spec: the page that it answers for each address
const pages = new Map<string, ReturnType<typeof pageAt>>();

// What happened, in its order: a clear of the history, and each page that was shown
let order: string[] = [];

let stop: () => void = () => undefined;

async function visit(url: string, account: string | null | undefined): Promise<void> {
  pages.set(url, pageAt(url, propsOf(account)));
  order = [];
  await router.visit(url);
  await vi.waitFor(() => expect(order).toContain(`show ${url}`));
}

beforeAll(async () => {
  window.sessionStorage.clear();
  window.history.replaceState(null, '', '/dashboard');

  http.setClient({
    request: (config) => {
      const url = new URL(String(config.url), ORIGIN);
      const page = pages.get(url.pathname) ?? pageAt(url.pathname, {});
      // A partial reload answers the props that it asked for only
      const only = config.headers?.['X-Inertia-Partial-Data'];
      const props =
        typeof only === 'string'
          ? Object.fromEntries(only.split(',').map((key) => [key, page.props[key as keyof typeof page.props]]))
          : page.props;

      return Promise.resolve({
        status: 200,
        headers: { 'x-inertia': 'true' },
        data: JSON.stringify({ ...page, props: { errors: {}, ...props } }),
      });
    },
  });

  const clearHistory = router.clearHistory.bind(router);
  vi.spyOn(router, 'clearHistory').mockImplementation(() => {
    order.push('clear');
    clearHistory();
  });

  stop = startHistoryOfOneAccount(propsOf(ADA));
  router.init({
    initialPage: pageAt('/dashboard', propsOf(ADA)),
    resolveComponent: (name) => Promise.resolve(name),
    swapComponent: ({ page }) => {
      order.push(`show ${page.url}`);

      return Promise.resolve();
    },
  });
  await vi.waitFor(() => expect(order).toContain('show /dashboard'));
});

beforeEach(() => {
  order = [];
});

afterAll(() => {
  stop();
  vi.restoreAllMocks();
});

describe('the account of a page', () => {
  it('is the cuid of the account, null for a visitor, and unknown for a page that does not say', () => {
    expect(accountOf(propsOf(ADA))).toBe(ADA);
    expect(accountOf(propsOf(null))).toBeNull();
    expect(accountOf({ auth: { user: undefined } })).toBeNull();
    expect(accountOf({})).toBeUndefined();
    expect(accountOf(null)).toBeUndefined();
    expect(accountOf({ auth: null })).toBeUndefined();
    expect(accountOf({ auth: {} })).toBeUndefined();
    expect(accountOf({ auth: { user: { name: 'No cuid' } } })).toBeUndefined();
    expect(accountOf({ auth: { user: { cuid: '' } } })).toBeUndefined();
    expect(accountOf({ auth: { user: { cuid: 42 } } })).toBeUndefined();
  });
});

describe('the history of a tab keeps the pages of one account', () => {
  it('keeps the cuid of the account of the first page, and nothing else of the account', () => {
    expect(window.sessionStorage.getItem(HISTORY_ACCOUNT_KEY)).toBe(ADA);
    expect(JSON.stringify({ ...window.sessionStorage })).not.toContain('member@example.com');
    expect(router.clearHistory).not.toHaveBeenCalled();
  });

  it('keeps the history for the next pages of the same account, a partial reload and a page that does not say', async () => {
    await visit('/community/harbour', ADA);
    await router.reload({ only: ['errors'] });
    await visit('/error', undefined);
    await visit('/user/profile', ADA);

    expect(router.clearHistory).not.toHaveBeenCalled();
    expect(window.sessionStorage.getItem(HISTORY_ACCOUNT_KEY)).toBe(ADA);
  });

  it('clears the history before it shows a page for no account, when the page before was of an account', async () => {
    // The session ended elsewhere: the next visit of this tab gets the sign-in page
    await visit('/login', null);

    expect(order).toEqual(['clear', 'show /login']);
    expect(window.sessionStorage.getItem(HISTORY_ACCOUNT_KEY)).toBeNull();
  });

  it('keeps the history from one page of a visitor to the next, and when a visitor signs in', async () => {
    await visit('/register', null);
    await visit('/home', GRACE);

    expect(router.clearHistory).not.toHaveBeenCalled();
    expect(order).toEqual(['show /home']);
    expect(window.sessionStorage.getItem(HISTORY_ACCOUNT_KEY)).toBe(GRACE);
  });

  it('clears the history before it shows a page of another account', async () => {
    await visit('/dashboard', ADA);

    expect(order).toEqual(['clear', 'show /dashboard']);
    expect(window.sessionStorage.getItem(HISTORY_ACCOUNT_KEY)).toBe(ADA);
  });

  it('clears the history at the first page of a new document, from what the tab stored', async () => {
    // A full load of the page: the memory of the document before is gone, the storage of the tab stays
    vi.resetModules();
    const afterLoad = await import('./history-account');
    const clear = vi.fn();

    expect(afterLoad.forgetHistoryOfAnotherAccount(propsOf(null), clear)).toBe(true);
    expect(clear).toHaveBeenCalledTimes(1);
    expect(window.sessionStorage.getItem(HISTORY_ACCOUNT_KEY)).toBeNull();

    // The same account after a full load: nothing to clear
    window.sessionStorage.setItem(HISTORY_ACCOUNT_KEY, GRACE);
    vi.resetModules();
    const again = await import('./history-account');
    expect(again.forgetHistoryOfAnotherAccount(propsOf(GRACE), clear)).toBe(false);
    expect(clear).toHaveBeenCalledTimes(1);
  });

  it('works from its memory in a browser that gives no storage', async () => {
    vi.resetModules();
    const withoutStorage = await import('./history-account');
    const clear = vi.fn();
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('no storage');
    });
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('no storage');
    });
    const removeItem = vi.spyOn(Storage.prototype, 'removeItem').mockImplementation(() => {
      throw new Error('no storage');
    });

    try {
      expect(withoutStorage.forgetHistoryOfAnotherAccount(propsOf(ADA), clear)).toBe(false);
      expect(withoutStorage.forgetHistoryOfAnotherAccount(propsOf(ADA), clear)).toBe(false);
      expect(withoutStorage.forgetHistoryOfAnotherAccount(propsOf(null), clear)).toBe(true);
      expect(withoutStorage.forgetHistoryOfAnotherAccount(propsOf(null), clear)).toBe(false);
      expect(clear).toHaveBeenCalledTimes(1);
    } finally {
      getItem.mockRestore();
      setItem.mockRestore();
      removeItem.mockRestore();
    }
  });
});
