import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import axe from 'axe-core';
import type { SpaceEvent } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import type { ToolkitLinkProps } from '@/Components/ui';
import { AccountTimeZoneProvider, ToolkitProvider } from '@/Components/ui';
import { EVENT_WORDS } from '@/Components/Events/event-words';
import { eventFixture, eventsPagination, eventsRoute } from '@/test-utils/events';
import MyEventsPage from './Index';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { auth: { user: { cuid: 'me', name: 'Mia Member' } } }, url: '/events' }),
  Link: ({
    children,
    href,
    preserveState: _preserveState,
    ...props
  }: {
    children: React.ReactNode;
    href: string;
    preserveState?: boolean;
  }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));
// The layout holds the main heading
vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children, pageTitle }: { children: React.ReactNode; pageTitle?: string }) => (
    <div>
      <h1>{pageTitle}</h1>
      <main>{children}</main>
    </div>
  ),
}));

function ToolkitLink({ href, children, className, 'aria-current': ariaCurrent }: ToolkitLinkProps) {
  return (
    <a href={href} className={className} aria-current={ariaCurrent}>
      {children}
    </a>
  );
}

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
});

const me = { cuid: 'me', name: 'Mia Member', username: 'mia', avatar_url: null };

function renderPage(items: SpaceEvent[], scope: 'upcoming' | 'past' = 'upcoming', showCommunity = false) {
  return render(
    <AppDataProvider>
      <AccountTimeZoneProvider value="Europe/Brussels">
        <ToolkitProvider linkComponent={ToolkitLink}>
          <MyEventsPage events={{ items, pagination: eventsPagination(null, items.length) }} scope={scope} showCommunity={showCommunity} />
        </ToolkitProvider>
      </AccountTimeZoneProvider>
    </AppDataProvider>,
  );
}

describe('the page "My events" (#1515, design 6.8)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('has one main heading, the sentence under it, and the list of the server, without a request', async () => {
    const list = vi.spyOn(knowiiApiClient.users.events, 'list');
    const fetchSpy = vi.spyOn(globalThis, 'fetch');
    const { container } = renderPage([
      eventFixture('y1', 'Weekly review', { my_answer: 'yes' }),
      eventFixture('h1', 'I host this', { host: me }),
    ]);

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(EVENT_WORDS.myEvents);
    expect(screen.getByText(EVENT_WORDS.myEventsIntro)).toBeInTheDocument();
    const rows = within(screen.getByRole('list', { name: EVENT_WORDS.upcomingList })).getAllByRole('listitem');
    expect(rows).toHaveLength(2);
    expect(within(screen.getByRole('article', { name: 'I host this' })).getByText(EVENT_WORDS.youHost)).toBeInTheDocument();
    // One community: the space alone
    expect(within(screen.getByRole('article', { name: 'Weekly review' })).getByText('Office hours')).toBeInTheDocument();
    expect(list).not.toHaveBeenCalled();
    expect(fetchSpy).not.toHaveBeenCalled();
    expect((await axe.run(container)).violations).toEqual([]);
  });

  it('opens the past list, and shows its empty state', async () => {
    const { container } = renderPage([], 'past', true);

    expect(screen.getByRole('link', { name: EVENT_WORDS.past })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByText(EVENT_WORDS.myEventsEmptyPastDescription)).toBeInTheDocument();
    expect((await axe.run(container)).violations).toEqual([]);
  });
});
