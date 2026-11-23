import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { SpaceEvent } from '@knowii/common';
import { EVENT_END, eventFixture } from '@/test-utils/events';
import { EventAfter } from './EventAfter';
import { EVENT_WORDS } from './event-words';

// An hour after the end of the event of the fixture, and an hour before its start
const AFTER = new Date('2026-11-18T18:00:00Z');
const BEFORE = new Date('2026-11-18T15:00:00Z');
const EDIT = '/community/tests/space/office-hours/event/weekly-review/edit#event-after';

// A link of one of the two video hosts of lessons, as the server sends its player (VideoEmbed::parse)
const PLAYER = {
  provider: 'youtube',
  id: 'dQw4w9WgXcQ',
  url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
  embed_url: 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
} as const;

const past = (changes: Record<string, unknown> = {}) => eventFixture('e1', 'Weekly review', { is_past: true, ...changes });

function renderAfter(event: SpaceEvent, { canUpdate = false, now = AFTER }: { canUpdate?: boolean; now?: Date } = {}) {
  return render(
    <main>
      <EventAfter event={event} canUpdate={canUpdate} editHref={EDIT} now={now} />
    </main>,
  );
}

async function expectNoViolation(container: HTMLElement) {
  const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
  expect(results.violations).toEqual([]);
}

describe('EventAfter, the section after the event (#1516, design 2.4 and 6.2 point 2)', () => {
  it('shows nothing before the end, on a draft and on a cancelled event, also with a replay and notes', () => {
    const full = { replay_url: PLAYER.url, replay: PLAYER, notes: 'The slides', notes_html: '<p>The slides</p>' };
    for (const [event, now] of [
      [past(full), BEFORE],
      [past({ ...full, state: 'draft' }), AFTER],
      [past({ ...full, state: 'cancelled' }), AFTER],
    ] as const) {
      const { container, unmount } = renderAfter(event, { canUpdate: true, now });
      expect(container.querySelector('section')).toBeNull();
      unmount();
    }
  });

  it('shows the player of a lesson that loads nothing before Play, then the notes, then the link of the host', async () => {
    const { container } = renderAfter(
      past({ replay_url: PLAYER.url, replay: PLAYER, notes: 'The **slides**', notes_html: '<p>The <strong>slides</strong></p>' }),
      { canUpdate: true },
    );

    const section = screen.getByRole('region', { name: EVENT_WORDS.afterEvent });
    expect(within(section).getByRole('heading', { level: 2, name: EVENT_WORDS.afterEvent })).toBeInTheDocument();
    // Nothing from the video host before Play: no frame
    expect(container.querySelector('iframe')).toBeNull();
    const play = within(section).getByRole('button', { name: /Play the video/ });
    const notes = within(section).getByRole('heading', { level: 3, name: EVENT_WORDS.notes });
    const edit = within(section).getByRole('link', { name: EVENT_WORDS.editReplayAndNotes });
    expect(play.compareDocumentPosition(notes) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(notes.compareDocumentPosition(edit) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(within(section).getByText('slides').tagName).toBe('STRONG');
    expect(edit).toHaveAttribute('href', EDIT);
    await expectNoViolation(container);

    await userEvent.click(play);
    expect(container.querySelector('iframe')).toHaveAttribute('src', `${PLAYER.embed_url}?autoplay=1`);
    expect(container.querySelector('iframe')).toHaveAttribute('title', 'Video: Weekly review');
  });

  it('shows another address as a plain link that opens in a new tab', async () => {
    const { container } = renderAfter(past({ replay_url: 'https://example.com/replay', replay: null }));

    const link = screen.getByRole('link', { name: `${EVENT_WORDS.watchReplay} (${EVENT_WORDS.opensInNewTab})` });
    expect(link).toHaveAttribute('href', 'https://example.com/replay');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    expect(container.querySelector('iframe, button')).toBeNull();
    await expectNoViolation(container);
  });

  it('shows nothing for a link that is not an absolute http or https address', () => {
    const { container } = renderAfter(past({ replay_url: ['java', 'script:alert(1)'].join(''), replay: null }));

    expect(container.querySelector('section')).toBeNull();
    expect(container.querySelector('a')).toBeNull();
  });

  it('tells a member that the host plans a replay, without a link yet', async () => {
    const { container } = renderAfter(past({ replay_expected: true }));

    expect(screen.getByText(EVENT_WORDS.replayPlanned)).toBeInTheDocument();
    await expectNoViolation(container);
  });

  it('gives a visitor the notes and nothing about the replay', async () => {
    const visitor = { can_view_replay: false, replay_url: null, replay: null, replay_expected: true };
    const { container, unmount } = renderAfter(past({ ...visitor, notes: 'The slides', notes_html: '<p>The slides</p>' }));

    expect(screen.getByRole('heading', { level: 3, name: EVENT_WORDS.notes })).toBeInTheDocument();
    expect(screen.queryByText(EVENT_WORDS.replayPlanned)).toBeNull();
    expect(screen.queryByText(/replay/i)).toBeNull();
    expect(container.querySelector('iframe, button')).toBeNull();
    await expectNoViolation(container);
    unmount();

    // Without notes, a visitor gets no section at all
    const { container: empty } = renderAfter(past(visitor));
    expect(empty.querySelector('section')).toBeNull();
  });

  it('gives the people who change the event "Add the replay and notes" when both are empty, and nothing more', async () => {
    const { container } = renderAfter(past(), { canUpdate: true });

    const section = screen.getByRole('region', { name: EVENT_WORDS.afterEvent });
    expect(within(section).getByRole('link', { name: EVENT_WORDS.addReplayAndNotes })).toHaveAttribute('href', EDIT);
    expect(within(section).queryByRole('heading', { level: 3 })).toBeNull();
    await expectNoViolation(container);
  });

  it('shows no section to a member when there is nothing in it', () => {
    const { container } = renderAfter(past());

    expect(container.querySelector('section')).toBeNull();
  });

  it('counts an event that ends now as over', () => {
    renderAfter(past({ notes: 'The slides', notes_html: '<p>The slides</p>' }), { now: new Date(EVENT_END) });

    expect(screen.getByRole('region', { name: EVENT_WORDS.afterEvent })).toBeInTheDocument();
  });
});
