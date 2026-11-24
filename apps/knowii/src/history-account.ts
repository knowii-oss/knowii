import { router } from '@inertiajs/react';

/*
 * The history of a tab keeps the pages of one account. Inertia keeps each page in the history of the browser, encrypted
 * with a key that stays in the storage of the tab (App\Support\BrowserHistory). When a page arrives for another account, or
 * for no account, after a page of an account, the tab forgets that key before it shows the page: the pages of before cannot
 * be read any more, and "Back" asks the server. So it does not matter how the session ended (a sign-out in another tab, a
 * session that expired, a change of the password on another device) or which tab sees it first.
 *
 * The tab keeps only the cuid of the account of its last page, in memory and in its `sessionStorage`, which also lives
 * through a full load of the page. A tab that makes no request keeps what it shows until its next navigation.
 */

export const HISTORY_ACCOUNT_KEY = 'knowii:history-account';

// The account of the last page of this document: a cuid, null for a visitor, undefined before the first page
let lastAccount: string | null | undefined;

/**
 * The account of a page: its cuid, null for a page of a visitor, undefined for a page that does not say (a page without
 * the prop `auth`, as the error page)
 */
export function accountOf(props: unknown): string | null | undefined {
  if (typeof props !== 'object' || props === null || !('auth' in props)) {
    return undefined;
  }
  const { auth } = props as { auth: unknown };
  if (typeof auth !== 'object' || auth === null || !('user' in auth)) {
    return undefined;
  }
  const { user } = auth as { user: unknown };
  if (user === null || user === undefined) {
    return null;
  }
  const cuid = typeof user === 'object' ? (user as { cuid?: unknown }).cuid : undefined;

  return typeof cuid === 'string' && cuid !== '' ? cuid : undefined;
}

function storedAccount(): string | null {
  try {
    return window.sessionStorage.getItem(HISTORY_ACCOUNT_KEY);
  } catch {
    // A browser that gives no storage: the memory of the document is all there is
    return null;
  }
}

function remember(account: string | null): void {
  lastAccount = account;
  try {
    if (account === null) {
      window.sessionStorage.removeItem(HISTORY_ACCOUNT_KEY);
    } else {
      window.sessionStorage.setItem(HISTORY_ACCOUNT_KEY, account);
    }
  } catch {
    // See storedAccount()
  }
}

/**
 * Call it with the props of a page before the page is shown. When the page before was of an account, and this one is of
 * another account or of no account, the history is cleared. Returns whether it was
 */
export function forgetHistoryOfAnotherAccount(props: unknown, clearHistory: () => void): boolean {
  const account = accountOf(props);
  if (account === undefined) {
    return false;
  }

  const last = lastAccount === undefined ? storedAccount() : lastAccount;
  const changed = last !== null && last !== account;
  if (changed) {
    clearHistory();
  }
  remember(account);

  return changed;
}

/**
 * Watch the account of each page: the first page of the document now, then every page that a visit brings, through the
 * event `beforeUpdate` of Inertia, which fires before the page goes in the history and on the screen. Call it once, with
 * the props of the first page, before the application starts. It returns the stop
 */
export function startHistoryOfOneAccount(initialProps: unknown): () => void {
  const clearHistory = () => router.clearHistory();
  forgetHistoryOfAnotherAccount(initialProps, clearHistory);

  return router.on('beforeUpdate', (event) => {
    forgetHistoryOfAnotherAccount(event.detail.page.props, clearHistory);
  });
}
