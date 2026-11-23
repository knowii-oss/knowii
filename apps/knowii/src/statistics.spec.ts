import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { http, router } from '@inertiajs/react';
import type { StatisticsPageProp } from '@knowii/common';
import { referrerOfTheDocument, startPageViews, statisticsOf } from './statistics';

/*
 * The page views of the statistics (#1316, ADR 0064), with the real router of Inertia: only the network is replaced. The
 * server gives the address of the page view in the prop `statistics` (tests/Feature/AnalyticsTest.php and
 * tests/Feature/SecretAddressesTest.php prove it). The sender posts that address, never the address of the browser, once
 * per visit of a page.
 */

const ENDPOINT = 'https://stats.example.com/api/v1/event';
const ORIGIN = window.location.origin;

function statistics(path: string): StatisticsPageProp {
  return { endpoint: ENDPOINT, domain: 'community.example.com', url: `${ORIGIN}${path}` };
}

function pageAt(url: string, props: Record<string, unknown>) {
  return { component: 'Page', url, version: 'spec', props: { errors: {}, ...props }, flash: {}, rescuedProps: [], rememberedState: {} };
}

// The server of this spec: the page that it answers for each address
const server = {
  pages: new Map<string, ReturnType<typeof pageAt>>(),
  requests: [] as { url: string; headers: Record<string, unknown> }[],
};

// The requests that the page sent to the statistics, in their order
let sent: { endpoint: string; init: RequestInit | undefined; body: Record<string, unknown> }[] = [];

const fetchMock = vi.fn((endpoint: string, init?: RequestInit) => {
  sent.push({ endpoint, init, body: JSON.parse(String(init?.body)) as Record<string, unknown> });
  return Promise.resolve(new Response(null, { status: 202 }));
});

const pageViews = () => sent;

let stop: () => void = () => undefined;

beforeAll(async () => {
  vi.stubGlobal('fetch', fetchMock);
  // The document was opened from a search page of another site, with a query of its own
  Object.defineProperty(document, 'referrer', { value: 'https://search.example.org/results?q=knowii', configurable: true });
  // The address of the browser holds a query and a fragment that the page view must never hold
  window.history.replaceState(null, '', '/start?email=ada%40example.com&utm_source=newsletter#private');

  http.setClient({
    request: (config) => {
      const url = new URL(String(config.url), ORIGIN);
      server.requests.push({ url: url.pathname + url.search, headers: { ...config.headers } });
      const page = server.pages.get(url.pathname) ?? pageAt(url.pathname, {});
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

  stop = startPageViews();
  router.init({
    initialPage: pageAt('/start?email=ada%40example.com&utm_source=newsletter', { statistics: statistics('/start?utm_source=newsletter') }),
    resolveComponent: () => Promise.resolve(() => null),
    swapComponent: () => Promise.resolve(),
  });
  await vi.waitFor(() => expect(sent).toHaveLength(1));
});

beforeEach(() => {
  server.requests = [];
});

afterAll(() => {
  stop();
  vi.unstubAllGlobals();
});

describe('the page views of the statistics (#1316)', () => {
  it('sends the first load once, with the address of the prop, never the address of the browser, and the referrer of another site', () => {
    const [first] = pageViews();
    expect(first?.endpoint).toBe(ENDPOINT);
    expect(first?.body).toEqual({
      name: 'pageview',
      url: `${ORIGIN}/start?utm_source=newsletter`,
      domain: 'community.example.com',
      referrer: 'https://search.example.org/results?q=knowii',
    });
    expect(first?.init).toMatchObject({ method: 'POST', headers: { 'Content-Type': 'text/plain' }, credentials: 'omit', keepalive: true });
    expect(JSON.stringify(first?.body)).not.toContain('email');
    expect(JSON.stringify(first?.body)).not.toContain('private');
    expect(JSON.stringify(first?.body)).not.toContain(window.location.href);
  });

  it('sends one page view for a visit to another page, without a referrer', async () => {
    sent = [];
    server.pages.set('/community/harbour', pageAt('/community/harbour', { statistics: statistics('/community/harbour') }));

    await router.visit('/community/harbour?token=abc');
    await vi.waitFor(() => expect(sent).toHaveLength(1));

    expect(pageViews()[0]?.body).toEqual({
      name: 'pageview',
      url: `${ORIGIN}/community/harbour`,
      domain: 'community.example.com',
      referrer: null,
    });
  });

  it('sends nothing for a partial reload, a reload of the same page and a visit to the same address', async () => {
    sent = [];

    await router.reload({ only: ['statistics'] });
    await router.reload();
    await router.visit(window.location.pathname + window.location.search);
    expect(server.requests).toHaveLength(3);
    expect(server.requests[0]?.headers['X-Inertia-Partial-Data']).toBe('statistics');

    // The next visit to another page still counts: the events of the reloads came and went
    server.pages.set('/dashboard', pageAt('/dashboard', { statistics: statistics('/dashboard') }));
    await router.visit('/dashboard');
    await vi.waitFor(() => expect(sent).toHaveLength(1));
    expect(pageViews()[0]?.body['url']).toBe(`${ORIGIN}/dashboard`);
  });

  it('sends one page view when the person goes back', async () => {
    sent = [];

    window.history.back();
    await vi.waitFor(() => expect(sent).toHaveLength(1));
    expect(pageViews()[0]?.body).toMatchObject({ url: `${ORIGIN}/community/harbour`, referrer: null });
  });

  it('sends nothing for a page whose prop is null or missing, for example a page at an address that holds a token', async () => {
    sent = [];
    server.pages.set('/login/link/secret-token', pageAt('/login/link/secret-token', { statistics: null }));
    server.pages.set('/no-prop', pageAt('/no-prop', {}));

    await router.visit('/login/link/secret-token');
    await router.visit('/no-prop');
    expect(server.requests).toHaveLength(2);
    expect(sent).toHaveLength(0);
  });

  it('sends nothing when a test tool drives the browser', async () => {
    sent = [];
    Object.defineProperty(navigator, 'webdriver', { value: true, configurable: true });
    server.pages.set('/members', pageAt('/members', { statistics: statistics('/members') }));

    try {
      await router.visit('/members');
      expect(server.requests).toHaveLength(1);
      expect(sent).toHaveLength(0);
    } finally {
      Object.defineProperty(navigator, 'webdriver', { value: false, configurable: true });
    }
  });
});

describe('the prop and the referrer of the statistics (#1316)', () => {
  it('reads the prop only in its shape', () => {
    expect(statisticsOf({ statistics: statistics('/a') })).toEqual(statistics('/a'));
    expect(statisticsOf({ statistics: null })).toBeNull();
    expect(statisticsOf({})).toBeNull();
    expect(statisticsOf(null)).toBeNull();
    expect(statisticsOf({ statistics: { endpoint: ENDPOINT, domain: 'community.example.com' } })).toBeNull();
    expect(statisticsOf({ statistics: { endpoint: '', domain: 'community.example.com', url: `${ORIGIN}/a` } })).toBeNull();
  });

  it('keeps a referrer of another site only: a page of this site, an empty value and a wrong value give none', () => {
    expect(referrerOfTheDocument('https://search.example.org/results?q=knowii', ORIGIN)).toBe(
      'https://search.example.org/results?q=knowii',
    );
    expect(referrerOfTheDocument(`${ORIGIN}/login/link/secret-token`, ORIGIN)).toBeNull();
    expect(referrerOfTheDocument('', ORIGIN)).toBeNull();
    expect(referrerOfTheDocument('not an address', ORIGIN)).toBeNull();
  });
});
