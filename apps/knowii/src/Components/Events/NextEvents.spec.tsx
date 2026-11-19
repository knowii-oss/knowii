import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import axe from 'axe-core';
import type { SpaceEvent } from '@knowii/common';
import { AccountTimeZoneProvider } from '@/Components/ui';
import { eventFixture, eventsCommunity, eventsRoute } from '@/test-utils/events';
import { NextEvents } from './NextEvents';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
});

const renderCard = (events: SpaceEvent[], showSpace = false, zone: string | null = 'America/New_York') =>
  render(
    <AccountTimeZoneProvider value={zone}>
      <main>
        <NextEvents community={eventsCommunity} events={events} showSpace={showSpace} />
      </main>
    </AccountTimeZoneProvider>,
  );

describe('"Next events" (#1514, design 6.7)', () => {
  it('links each title to the page of the event in its own space', () => {
    renderCard([
      eventFixture('e1', 'Weekly review'),
      eventFixture('e2', 'Workshop', { space: { cuid: 's2', slug: 'workshops', name: 'Workshops' } }),
    ]);

    expect(screen.getByRole('link', { name: 'Weekly review' })).toHaveAttribute(
      'href',
      '/community/tests/space/office-hours/event/weekly-review',
    );
    expect(screen.getByRole('link', { name: 'Workshop' })).toHaveAttribute('href', '/community/tests/space/workshops/event/workshop');
    expect(screen.getByRole('heading', { level: 2, name: 'Next events' })).toBeInTheDocument();
  });

  it('writes the time of each event in the zone of the reader: a time element, the short form, and the long form for a screen reader', () => {
    renderCard([eventFixture('e1', 'Weekly review')]);

    const time = document.querySelector('time') as HTMLElement;
    expect(time).toHaveAttribute('datetime', '2026-11-18T16:00:00.000Z');
    expect(time).not.toHaveAttribute('title');
    expect(within(time).getByText('Wed 18 Nov, 11:00 Eastern Standard Time')).toHaveAttribute('aria-hidden', 'true');
    expect(within(time).getByText(/^Wednesday 18 November(?: 2026)?, 11:00 to 12:00 Eastern Standard Time$/)).toHaveClass('sr-only');
  });

  it('names the space only where it is asked: on the home of a member', () => {
    const { unmount } = renderCard([eventFixture('e1', 'Weekly review')], true);
    expect(screen.getByText('Office hours')).toBeInTheDocument();
    unmount();

    renderCard([eventFixture('e1', 'Weekly review')], false);
    expect(screen.queryByText('Office hours')).toBeNull();
  });

  it('labels a cancelled event with the word "Cancelled"', () => {
    renderCard([eventFixture('e1', 'Weekly review', { state: 'cancelled' }), eventFixture('e2', 'Workshop')]);

    const lines = screen.getAllByRole('listitem');
    expect(within(lines[0] as HTMLElement).getByText('Cancelled')).toBeInTheDocument();
    expect(within(lines[1] as HTMLElement).queryByText('Cancelled')).toBeNull();
  });

  it('never shows the meeting address or the place of an event', () => {
    renderCard([eventFixture('e1', 'Weekly review', { meeting_url: 'https://meet.example.com/secret', place: 'Room 2' })], true);

    expect(screen.queryByText(/meet\.example\.com/)).toBeNull();
    expect(screen.queryByText('Room 2')).toBeNull();
    expect(screen.getAllByRole('link')).toHaveLength(1);
  });

  it('renders nothing without an event', () => {
    const { container } = renderCard([]);

    expect(container.querySelector('main')).toBeEmptyDOMElement();
  });

  it('has no automated violation (axe)', async () => {
    const { container } = renderCard([eventFixture('e1', 'Weekly review', { state: 'cancelled' }), eventFixture('e2', 'Workshop')], true);

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
