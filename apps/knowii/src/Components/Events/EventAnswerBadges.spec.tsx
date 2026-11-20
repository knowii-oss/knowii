import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import axe from 'axe-core';
import { EVENT_TEST_NOW, eventFixture } from '@/test-utils/events';
import { EventAnswerBadges } from './EventAnswerBadges';

const show = (changes: Record<string, unknown>, now: Date = EVENT_TEST_NOW) =>
  render(
    <p>
      <EventAnswerBadges event={eventFixture('e1', 'Weekly review', changes)} now={now} />
    </p>,
  );
const labels = () => Array.from(document.querySelectorAll('span.rounded-md.border.text-xs')).map((badge) => badge.textContent);
const badge = (words: string) => screen.getByText(words).closest('span.rounded-md.border.text-xs') as HTMLElement;

describe('the labels of the answer and of the places (#1515, design 6.1 point 4)', () => {
  it.each([
    ['yes', 'You said Yes', 'border-success'],
    ['replay', 'You said Replay only', 'border-secondary-text'],
    ['no', 'You said No', 'border-border-strong'],
  ])('writes the answer "%s" in words, with its variant (rule 30)', (answer, words, border) => {
    show({ my_answer: answer });

    expect(labels()).toEqual([words]);
    expect(badge(words)).toHaveClass(border);
  });

  it('gives only "You said Yes" a check icon, hidden from screen readers (rule 30)', () => {
    const { unmount } = show({ my_answer: 'yes' });
    const icon = badge('You said Yes').querySelector('svg');
    expect(icon).not.toBeNull();
    expect(icon?.closest('[aria-hidden="true"]')).not.toBeNull();
    unmount();

    show({ my_answer: 'no' });
    expect(badge('You said No').querySelector('svg')).toBeNull();
  });

  it.each([
    ['a cancelled event', { state: 'cancelled', can_answer: false }],
    ['a past event', { is_past: true, can_answer: false }],
  ])('keeps the answer on %s, without the places (rules 30 and 31)', (_name, changes) => {
    show({ ...changes, my_answer: 'yes', places_left: 5 });

    expect(labels()).toEqual(['You said Yes']);
  });

  it.each([
    [28, '28 places left'],
    [2, '2 places left'],
    [1, '1 place left'],
    [0, 'Full'],
  ])('writes the places of a published event that is not over: %i (rule 31)', (places, words) => {
    show({ places_left: places });

    expect(labels()).toEqual([words]);
    // Always neutral, with no icon: no urgency
    expect(badge(words)).toHaveClass('border-border-strong');
    expect(badge(words).querySelector('svg')).toBeNull();
  });

  it('shows no places for a draft, and once the end passed on an open page (rule 31)', () => {
    const { unmount } = show({ state: 'draft', can_answer: false, places_left: 5 });
    expect(labels()).toEqual([]);
    unmount();

    show({ places_left: 5 }, new Date('2026-11-18T17:00:00Z'));
    expect(labels()).toEqual([]);
  });

  it('renders nothing without an answer and without a limit: a visitor, a person who is not a member (rule 32)', () => {
    const { container } = show({ my_answer: null, places_left: null, can_answer: false });

    expect(container.querySelector('p')).toBeEmptyDOMElement();
  });

  it('writes the answer before the places, and has no control (rule 33)', async () => {
    const { container } = show({ my_answer: 'replay', places_left: 0, replay_expected: true });

    expect(labels()).toEqual(['You said Replay only', 'Full']);
    expect(screen.queryByRole('button')).toBeNull();
    expect(screen.queryByRole('radio')).toBeNull();
    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
