/**
 * True when a link points to the page that is open (#686). Compares the paths only: the query string and the hash do
 * not change the page. With `match: 'prefix'`, the pages below the link count too (for example a community and its
 * spaces).
 */
export function isCurrentUrl(href: string, currentUrl: string, match: 'exact' | 'prefix' = 'exact'): boolean {
  const path = normalizePath(href);
  const current = normalizePath(currentUrl);
  if (match === 'exact') {
    return path === current;
  }
  return current === path || current.startsWith(path === '/' ? '/' : `${path}/`);
}

function normalizePath(url: string): string {
  const { pathname } = new URL(url, 'http://localhost');
  const trimmed = pathname.replace(/\/+$/, '');
  return trimmed === '' ? '/' : trimmed;
}
