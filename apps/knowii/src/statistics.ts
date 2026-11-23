import { router } from '@inertiajs/react';
import type { StatisticsPageProp } from '@knowii/common';

/*
 * The page views of the statistics (#1316, ADR 0064). The application loads no script of another host for the statistics:
 * the shared prop `statistics` (App\Support\Analytics::pageProp()) gives the event address of the proxy, the site and the
 * address of the page view, which the server cleans. It is null at an address that holds a link token or a signature.
 */

/**
 * The prop of a page, or null when it is missing or has another shape
 */
export function statisticsOf(props: unknown): StatisticsPageProp | null {
  const statistics = typeof props === 'object' && props !== null ? (props as { statistics?: unknown }).statistics : null;
  if (typeof statistics !== 'object' || statistics === null) {
    return null;
  }
  const { endpoint, domain, url } = statistics as Partial<Record<keyof StatisticsPageProp, unknown>>;

  return typeof endpoint === 'string' && typeof domain === 'string' && typeof url === 'string' && endpoint !== ''
    ? { endpoint, domain, url }
    : null;
}

/**
 * The referrer of the first page view of a document: the address that the browser gives, only when it is of another
 * site. A page of this site is never a referrer, so an address of this site never reaches the statistics
 */
export function referrerOfTheDocument(referrer: string, origin: string): string | null {
  if (referrer === '') {
    return null;
  }
  try {
    return new URL(referrer).origin === origin ? null : referrer;
  } catch {
    return null;
  }
}

/**
 * Post one page view, as the Events API of Plausible documents it. The content type `text/plain` needs no preflight
 * request. The address is the one of the prop, never the address of the browser. A failure changes nothing for the page
 */
export function sendPageView(statistics: StatisticsPageProp, referrer: string | null): void {
  void fetch(statistics.endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'text/plain' },
    body: JSON.stringify({ name: 'pageview', url: statistics.url, domain: statistics.domain, referrer }),
    credentials: 'omit',
    keepalive: true,
    referrerPolicy: 'no-referrer',
  }).catch(() => {
    // The statistics never stop a page
  });
}

/**
 * Send one page view per visit of a page, through the event `navigate` of Inertia: it fires once for the first load of a
 * document (`initial`), once for each visit to another address (`visit`), and once for back and forward (`history`).
 * Inertia does not fire it for a visit that replaces the address of the page: a partial reload, a reload of the same page,
 * a poll and a live update send nothing. Nothing either when the prop is null or missing, and when a test tool drives the
 * browser. Call it once, before the application starts, so that the first load is counted. It returns the stop
 */
export function startPageViews(): () => void {
  let firstOfTheDocument = true;

  return router.on('navigate', (event) => {
    const referrer = firstOfTheDocument ? referrerOfTheDocument(document.referrer, window.location.origin) : null;
    firstOfTheDocument = false;

    const statistics = statisticsOf(event.detail.page.props);
    if (statistics === null || navigator.webdriver) {
      return;
    }
    sendPageView(statistics, referrer);
  });
}
