import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import axe from 'axe-core';
import type { SpaceEvent } from '@knowii/common';
import { AccountTimeZoneProvider } from '@/Components/ui';
import { EVENT_TEST_NOW, eventFixture, eventsCommunity, eventsRoute } from '@/test-utils/events';
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

  describe('the labels of the answer and of the places (#1515, rules 29 to 33)', () => {
    const labels = (line: HTMLElement) =>
      Array.from(line.querySelectorAll('span.rounded-md.border.text-xs')).map((badge) => badge.textContent);

    // A week before the events: they still take answers
    beforeEach(() => {
      vi.useFakeTimers({ toFake: ['Date'] });
      vi.setSystemTime(EVENT_TEST_NOW);
    });
    afterEach(() => {
      vi.useRealTimers();
    });

    it('writes the answer of the member and the places next to "Cancelled"', () => {
      renderCard([
        eventFixture('e1', 'Weekly review', { state: 'cancelled', can_answer: false, my_answer: 'yes', places_left: 5 }),
        eventFixture('e2', 'Workshop', { my_answer: 'replay', replay_expected: true, places_left: 28 }),
        eventFixture('e3', 'Dinner', { my_answer: 'no', places_left: 0 }),
      ]);

      const lines = screen.getAllByRole('listitem');
      // A cancelled event takes no answers: its places do not show, the old answer does
      expect(labels(lines[0] as HTMLElement)).toEqual(['Cancelled', 'You said Yes']);
      expect(labels(lines[1] as HTMLElement)).toEqual(['You said Replay only', '28 places left']);
      expect(labels(lines[2] as HTMLElement)).toEqual(['You said No', 'Full']);
      // The labels sit in the paragraph of the title
      expect(
        within(lines[0] as HTMLElement)
          .getByText('Cancelled')
          .closest('p'),
      ).toBe(
        within(lines[0] as HTMLElement)
          .getByText('You said Yes')
          .closest('p'),
      );
    });

    it('writes "1 place left" for one place, and nothing without a limit and without an answer', () => {
      renderCard([eventFixture('e1', 'Weekly review', { places_left: 1 }), eventFixture('e2', 'Workshop')]);

      const lines = screen.getAllByRole('listitem');
      expect(labels(lines[0] as HTMLElement)).toEqual(['1 place left']);
      expect(labels(lines[1] as HTMLElement)).toEqual([]);
    });

    it('shows no label on the public page: a visitor gets no answer and no places from the server (rule 32)', () => {
      renderCard([eventFixture('e1', 'Weekly review', { my_answer: null, can_answer: false, places_left: null })]);

      expect(document.querySelector('span.rounded-md.border.text-xs')).toBeNull();
    });

    it('shows no places once the event is over: the list is the one of the page load', () => {
      vi.setSystemTime(new Date('2026-11-18T17:00:00Z'));
      renderCard([eventFixture('e1', 'Weekly review', { places_left: 5, my_answer: 'yes' })]);

      expect(labels(screen.getByRole('listitem'))).toEqual(['You said Yes']);
    });

    it('has no answer control: a member answers on the page of the event (rule 33)', () => {
      renderCard([eventFixture('e1', 'Weekly review', { places_left: 28, replay_expected: true })]);

      expect(screen.queryByRole('radio')).toBeNull();
      expect(screen.queryByRole('button')).toBeNull();
      expect(screen.getAllByRole('link')).toHaveLength(1);
    });

    it('has no automated violation with the labels (axe)', async () => {
      const { container } = renderCard(
        [
          eventFixture('e1', 'Weekly review', { state: 'cancelled', can_answer: false, my_answer: 'yes' }),
          eventFixture('e2', 'Workshop', { my_answer: 'replay', replay_expected: true, places_left: 28 }),
          eventFixture('e3', 'Dinner', { places_left: 0 }),
        ],
        true,
      );

      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
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
