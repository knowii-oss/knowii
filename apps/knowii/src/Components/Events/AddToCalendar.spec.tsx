import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { eventFixture } from '@/test-utils/events';
import { AddToCalendar } from './AddToCalendar';
import { EVENT_WORDS } from './event-words';

const FILE_URL = '/api/v1/communities/c1/spaces/s1/events/e1/calendar-file';

describe('"Add to calendar" (#1515, design 6.5)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('is one plain link to the calendar file of the event, with "download", and its words as its name', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch');
    const { container } = render(
      <main>
        <AddToCalendar communityCuid="c1" spaceCuid="s1" event={eventFixture('e1', 'Weekly review office hour')} isMember={true} />
      </main>,
    );

    const link = screen.getByRole('link', { name: 'Add to calendar' });
    expect(link.tagName).toBe('A');
    expect(link).toHaveAttribute('href', FILE_URL);
    expect(link).toHaveAttribute('download');
    // A secondary button of the toolkit, 44 px high, with a calendar icon that screen readers skip
    expect(link.className).toContain('h-11');
    expect(link.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
    expect(screen.queryByRole('button')).toBeNull();
    // It loads a file from an address: no script sends a request
    expect(fetchSpy).not.toHaveBeenCalled();
    expect((await axe.run(container)).violations).toEqual([]);
  });

  it('names the event after its visible words on a row of a list', async () => {
    const { container } = render(
      <main>
        <AddToCalendar
          communityCuid="c1"
          spaceCuid="s1"
          event={eventFixture('e1', 'Weekly review office hour')}
          isMember={true}
          withTitle={true}
        />
      </main>,
    );

    const link = screen.getByRole('link', { name: 'Add to calendar: Weekly review office hour' });
    // The visible text starts the name: the title is for screen readers only
    expect(link).toHaveTextContent(EVENT_WORDS.addToCalendar);
    expect(link).not.toHaveTextContent('Weekly review office hour');
    expect(link).toHaveAttribute('aria-label', 'Add to calendar: Weekly review office hour');
    expect((await axe.run(container)).violations).toEqual([]);
  });

  it('is reached with Tab and shows its focus', async () => {
    render(<AddToCalendar communityCuid="c1" spaceCuid="s1" event={eventFixture('e1', 'Weekly review office hour')} isMember={true} />);

    await userEvent.tab();

    expect(screen.getByRole('link', { name: 'Add to calendar' })).toHaveFocus();
  });

  it('stays on a cancelled event and on an event that ended', () => {
    const { rerender } = render(
      <AddToCalendar communityCuid="c1" spaceCuid="s1" event={eventFixture('e1', 'Cancelled', { state: 'cancelled' })} isMember={true} />,
    );
    expect(screen.getByRole('link', { name: 'Add to calendar' })).toBeInTheDocument();

    rerender(<AddToCalendar communityCuid="c1" spaceCuid="s1" event={eventFixture('e1', 'Over', { is_past: true })} isMember={true} />);
    expect(screen.getByRole('link', { name: 'Add to calendar' })).toBeInTheDocument();
  });

  it('gives no element and no text to a visitor, to a person who is not a member, and to every reader of a draft', () => {
    const { container, rerender } = render(
      <AddToCalendar communityCuid="c1" spaceCuid="s1" event={eventFixture('e1', 'Weekly review')} isMember={false} />,
    );
    expect(container).toBeEmptyDOMElement();

    rerender(
      <AddToCalendar communityCuid="c1" spaceCuid="s1" event={eventFixture('e1', 'Weekly review', { state: 'draft' })} isMember={true} />,
    );
    expect(container).toBeEmptyDOMElement();
    expect(screen.queryByText(EVENT_WORDS.addToCalendar)).toBeNull();
  });
});
