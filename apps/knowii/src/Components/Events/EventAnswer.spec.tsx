import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { ApiResult, EventAnswer as StoredAnswer, EventAnswerValue, SpaceEvent } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { VisitorProvider } from '@/Components/Reader/Reader';
import { EVENT_TEST_NOW, eventFixture, eventsCommunity, eventsRoute, eventsSpace } from '@/test-utils/events';
import { EventAnswer } from './EventAnswer';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
  // jsdom has no scrollIntoView: the first radio takes the focus after a clear
  Element.prototype.scrollIntoView = vi.fn();
});

type Reader = 'member' | 'outsider' | 'visitor';
type SetResult = ApiResult<StoredAnswer>;
type Failure = Extract<SetResult, { ok: false }>;

const PRIVACY = 'The host and the team see your answer (Yes or Replay only). Other members do not.';
const saved = (answer: EventAnswerValue): SetResult => ({
  ok: true,
  message: 'Your answer',
  data: { answer, updated_at: new Date('2026-11-11T09:00:00Z') },
});
const refused = (status: number, message: string, errors: Record<string, string[]> = {}): Failure => ({
  ok: false,
  status,
  message,
  errors,
});
const cleared: ApiResult<undefined> = { ok: true, message: '', data: undefined };

// An event with a limit and a replay: the three answers
const open = (changes: Record<string, unknown> = {}) =>
  eventFixture('e1', 'Weekly review', { replay_expected: true, places_left: 28, ...changes });

function mount(event: SpaceEvent, reader: Reader = 'member', now: Date = EVENT_TEST_NOW) {
  const calls = { onSaving: vi.fn(), onSaved: vi.fn(), onGone: vi.fn() };
  const tree = (shown: SpaceEvent) => (
    <VisitorProvider value={reader === 'visitor'}>
      <main>
        <button type="button">Before</button>
        <EventAnswer community={eventsCommunity} space={eventsSpace} event={shown} isMember={reader === 'member'} now={now} {...calls} />
        <button type="button">After</button>
      </main>
    </VisitorProvider>
  );
  const view = render(tree(event));
  return { ...view, ...calls, show: (next: SpaceEvent) => view.rerender(tree(next)) };
}

/**
 * A save that the test ends when it wants
 */
function slowSet() {
  const answers: Array<(result: SetResult) => void> = [];
  const spy = vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockImplementation(
    () =>
      new Promise<SetResult>((resolve) => {
        answers.push(resolve);
      }),
  );
  return { spy, answer: async (index: number, result: SetResult) => act(async () => answers[index]?.(result)) };
}

const group = () => screen.getByRole('radiogroup', { name: 'Your answer' });
const radio = (name: string) => within(group()).getByRole('radio', { name });
const checked = () =>
  within(group())
    .getAllByRole('radio')
    .filter((input) => (input as HTMLInputElement).checked)
    .map((input) => (input as HTMLInputElement).value);
const sent = (spy: { mock: { calls: unknown[][] } }) => spy.mock.calls.map((call) => call[3]);
const noViolation = async (container: Element) => {
  const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
  expect(results.violations.map((violation) => violation.id)).toEqual([]);
};

describe('the answer of a member on the page of an event (#1515, design 6.4 and 11.4)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('the group (rules 2 to 6)', () => {
    it('shows the group to a member of an event that takes answers and is not over (rule 2)', () => {
      mount(open());

      expect(group()).toBeInTheDocument();
    });

    it('is a fieldset with the visible legend, and one radio group that the legend names one time (rule 3)', () => {
      const { container } = mount(open());

      const legend = container.querySelector('fieldset > legend');
      expect(legend).toHaveTextContent('Your answer');
      expect(legend).toBeVisible();
      expect(group()).toHaveAttribute('aria-labelledby', legend?.id);
      expect(container.querySelector('fieldset')?.contains(group())).toBe(true);
      // One named group for a screen reader, not two: the fieldset gives its name to the radio group and keeps none
      expect(screen.queryByRole('group', { name: 'Your answer' })).toBeNull();
      expect(screen.getAllByRole('radiogroup')).toHaveLength(1);
    });

    it.each([
      ['with a replay', { replay_expected: true }, ['Yes', 'Replay only', 'No']],
      ['without a replay', { replay_expected: false }, ['Yes', 'No']],
      // The host removed the replay: the member still reads their answer, and chooses Yes or No when they want
      [
        'without a replay, for a member who answered "Replay only"',
        { replay_expected: false, my_answer: 'replay' },
        ['Yes', 'Replay only', 'No'],
      ],
    ])('offers the answers in their order %s (rule 4)', (_name, changes, labels) => {
      mount(open(changes));

      expect(
        within(group())
          .getAllByRole('radio')
          .map((input) => input.closest('label')?.textContent),
      ).toEqual(labels);
    });

    it('checks no radio without an answer, and the first radio is the one tab stop of the group (rule 5)', async () => {
      mount(open());
      expect(checked()).toEqual([]);

      screen.getByRole('button', { name: 'Before' }).focus();
      await userEvent.tab();
      expect(radio('Yes')).toHaveFocus();
      await userEvent.tab();
      expect(screen.getByRole('button', { name: 'After' })).toHaveFocus();
    });

    it.each([
      ['before an answer', {}],
      ['after an answer', { my_answer: 'yes' }],
    ])('says who sees the answer under the group, as its description, %s (rule 6)', (_name, changes) => {
      mount(open(changes));

      expect(group()).toHaveAccessibleDescription(PRIVACY);
      expect(screen.getByText(PRIVACY)).toBeVisible();
    });
  });

  describe('a save (rules 7 to 11)', () => {
    it('sends the choice at once and checks its radio at once (rule 7)', async () => {
      const { spy } = slowSet();
      mount(open());

      await userEvent.click(radio('Yes'));

      expect(spy).toHaveBeenCalledTimes(1);
      expect(spy).toHaveBeenCalledWith('c1', 's1', 'e1', 'yes');
      // Before the server answered
      expect(checked()).toEqual(['yes']);
    });

    it('is busy while the save runs, with no radio disabled and the focus where it was (rule 8)', async () => {
      const { answer } = slowSet();
      const { onSaving } = mount(open());

      await userEvent.click(radio('Yes'));

      expect(group()).toHaveAttribute('aria-busy', 'true');
      for (const input of within(group()).getAllByRole('radio')) {
        expect(input).not.toBeDisabled();
      }
      expect(radio('Yes')).toHaveFocus();
      expect(onSaving).toHaveBeenLastCalledWith(true);

      await answer(0, saved('yes'));
      expect(group()).not.toHaveAttribute('aria-busy');
      expect(radio('Yes')).toHaveFocus();
      expect(onSaving).toHaveBeenLastCalledWith(false);
    });

    it('sends the last choice after the running save, and no choice in between: Yes, then No through "Replay only" (rule 9)', async () => {
      const { spy, answer } = slowSet();
      const { onSaved } = mount(open());

      // The save of "Yes" runs. The arrow keys move the choice to "Replay only", then to "No"
      await userEvent.click(radio('Yes'));
      await userEvent.keyboard('{ArrowRight}');
      await userEvent.keyboard('{ArrowRight}');

      expect(sent(spy)).toEqual(['yes']);
      expect(checked()).toEqual(['no']);
      expect(radio('No')).toHaveFocus();

      await answer(0, saved('yes'));
      // "Replay only" was never sent, "No" is sent one time
      expect(sent(spy)).toEqual(['yes', 'no']);
      expect(group()).toHaveAttribute('aria-busy', 'true');
      expect(screen.getByRole('status')).toHaveTextContent('Saving');
      expect(onSaved).not.toHaveBeenCalled();

      await answer(1, saved('no'));
      expect(sent(spy)).toEqual(['yes', 'no']);
      expect(checked()).toEqual(['no']);
      expect(screen.getByRole('status')).toHaveTextContent('Saved: No');
      // One load of the event, after the last save
      expect(onSaved).toHaveBeenCalledTimes(1);
    });

    it('sends nothing more when the member comes back to the choice that is being saved (rule 9)', async () => {
      const { spy, answer } = slowSet();
      mount(open());

      await userEvent.click(radio('Yes'));
      await userEvent.keyboard('{ArrowRight}');
      await userEvent.keyboard('{ArrowLeft}');
      await answer(0, saved('yes'));

      expect(sent(spy)).toEqual(['yes']);
      expect(checked()).toEqual(['yes']);
      expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes');
    });

    it('keeps the status region in the page before a save, empty (rule 10)', () => {
      mount(open());

      const status = screen.getByRole('status');
      expect(status).toBeEmptyDOMElement();
      expect(status).toHaveAttribute('aria-live', 'polite');
    });

    it.each([
      ['Yes', 'yes', 'Saved: Yes'],
      ['Replay only', 'replay', 'Saved: Replay only'],
      ['No', 'no', 'Saved: No'],
    ] as const)('says "Saving", then the saved answer, in the same region: %s (rule 10)', async (label, value, words) => {
      const { answer } = slowSet();
      mount(open());
      const status = screen.getByRole('status');

      await userEvent.click(radio(label));
      expect(status).toHaveTextContent('Saving');

      await answer(0, saved(value));
      expect(screen.getByRole('status')).toBe(status);
      expect(status).toHaveTextContent(words);
    });

    it('asks the page for the event again after a save, one time (rule 11), and then shows what the server gave', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(saved('yes'));
      const { onSaved, onGone, show } = mount(open());

      await userEvent.click(radio('Yes'));
      await waitFor(() => expect(onSaved).toHaveBeenCalledTimes(1));
      expect(onGone).not.toHaveBeenCalled();

      // The page gives the event again: one place less, and the answer of the server
      show(open({ my_answer: 'yes', places_left: 27 }));
      expect(checked()).toEqual(['yes']);
      expect(screen.getByText('27 places left')).toBeInTheDocument();
      // The status and the focus stay
      expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes');
      expect(radio('Yes')).toHaveFocus();
    });
  });

  describe('the event of the page changes while a save runs, and after it', () => {
    it('keeps the choice that is being saved when a new event arrives, then shows the answer of the server', async () => {
      const { spy, answer } = slowSet();
      const { show, onSaved } = mount(open());

      await userEvent.click(radio('Yes'));
      // The page gives an event with another answer (a second tab of the member), while "Yes" is saved
      show(open({ my_answer: 'no', places_left: 27 }));

      expect(checked()).toEqual(['yes']);
      expect(group()).toHaveAttribute('aria-busy', 'true');
      expect(screen.getByRole('status')).toHaveTextContent('Saving');
      expect(screen.getByText('27 places left')).toBeInTheDocument();

      await answer(0, saved('yes'));
      expect(sent(spy)).toEqual(['yes']);
      expect(checked()).toEqual(['yes']);
      expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes');
      expect(onSaved).toHaveBeenCalledTimes(1);

      // The load after the save: the answer of the server
      show(open({ my_answer: 'yes', places_left: 26 }));
      expect(checked()).toEqual(['yes']);
      expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes');
    });

    it('checks the answer of the event that arrived during the save when the save is refused: it is newer than the answer of before', async () => {
      const { answer } = slowSet();
      const { show } = mount(open({ places_left: 1 }));

      await userEvent.click(radio('Yes'));
      show(open({ my_answer: 'no', places_left: 0 }));
      expect(checked()).toEqual(['yes']);

      await answer(0, refused(422, 'This event is full.'));
      expect(screen.getByRole('alert')).toHaveTextContent('This event is full.');
      expect(checked()).toEqual(['no']);

      // The load after the refusal gives the same answer: nothing moves
      show(open({ my_answer: 'no', places_left: 0, updated_at: '2026-11-11T08:00:00.000000Z' }));
      expect(checked()).toEqual(['no']);
      expect(screen.getByRole('button', { name: 'Clear my answer' })).toBeInTheDocument();
    });

    it('sends "Clear my answer" after the save that runs, one time, and ends without an answer', async () => {
      const { spy, answer } = slowSet();
      const clear = vi.spyOn(knowiiApiClient.communities.events.answer, 'clear').mockResolvedValue(cleared);
      const { onSaved } = mount(open());

      await userEvent.click(radio('Yes'));
      await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));

      // Nothing is sent while "Yes" is saved. The screen shows the last choice: no answer
      expect(clear).not.toHaveBeenCalled();
      expect(checked()).toEqual([]);
      expect(screen.queryByRole('button', { name: 'Clear my answer' })).toBeNull();
      expect(radio('Yes')).toHaveFocus();
      expect(group()).toHaveAttribute('aria-busy', 'true');

      await answer(0, saved('yes'));
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Answer cleared'));
      expect(sent(spy)).toEqual(['yes']);
      expect(clear).toHaveBeenCalledTimes(1);
      expect(checked()).toEqual([]);
      expect(group()).not.toHaveAttribute('aria-busy');
      expect(onSaved).toHaveBeenCalledTimes(1);
    });

    it('sends nothing for "Clear my answer" when the save that runs is refused: the server holds no answer', async () => {
      const { answer } = slowSet();
      const clear = vi.spyOn(knowiiApiClient.communities.events.answer, 'clear').mockResolvedValue(cleared);
      mount(open({ places_left: 1 }));

      await userEvent.click(radio('Yes'));
      await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));
      await answer(0, refused(422, 'This event is full.'));

      expect(clear).not.toHaveBeenCalled();
      expect(checked()).toEqual([]);
      expect(screen.getByRole('alert')).toHaveTextContent('This event is full.');
    });

    it('empties "Saved: Yes" when the event brings an answer that this page did not save', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(saved('yes'));
      const { show } = mount(open());

      await userEvent.click(radio('Yes'));
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes'));
      // The load after the own save: the same answer, the status stays
      show(open({ my_answer: 'yes', places_left: 27 }));
      expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes');

      // A second tab of the member answered "No": the page gets the event again
      show(open({ my_answer: 'no', places_left: 28 }));
      expect(checked()).toEqual(['no']);
      expect(screen.getByRole('status')).toBeEmptyDOMElement();
    });

    it('empties "Answer cleared" when the event brings an answer again', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'clear').mockResolvedValue(cleared);
      const { show } = mount(open({ my_answer: 'no' }));

      await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Answer cleared'));
      show(open({ my_answer: null }));
      expect(screen.getByRole('status')).toHaveTextContent('Answer cleared');

      show(open({ my_answer: 'replay' }));
      expect(checked()).toEqual(['replay']);
      expect(screen.getByRole('status')).toBeEmptyDOMElement();
    });
  });

  describe('the member leaves the page while a save runs', () => {
    it('calls nothing when the save ends after the page is gone', async () => {
      const { answer } = slowSet();
      const { unmount, onSaving, onSaved, onGone } = mount(open());

      await userEvent.click(radio('Yes'));
      expect(onSaving).toHaveBeenCalledTimes(1);
      unmount();
      await answer(0, saved('yes'));

      // The next page has no event to load again
      expect(onSaved).not.toHaveBeenCalled();
      expect(onGone).not.toHaveBeenCalled();
      expect(onSaving).toHaveBeenCalledTimes(1);
    });

    it.each([
      ['a refusal', refused(422, 'This event is full.')],
      ['an event that is gone (404)', refused(404, 'Not found')],
    ])('calls nothing after %s that arrives when the page is gone', async (_name, failure) => {
      const { answer } = slowSet();
      const { unmount, onSaving, onSaved, onGone } = mount(open());

      await userEvent.click(radio('Yes'));
      unmount();
      await answer(0, failure);

      expect(onSaved).not.toHaveBeenCalled();
      expect(onGone).not.toHaveBeenCalled();
      expect(onSaving).toHaveBeenCalledTimes(1);
    });

    it('still sends the last choice of the member: no choice is lost', async () => {
      const { spy, answer } = slowSet();
      const { unmount, onSaved } = mount(open());

      await userEvent.click(radio('Yes'));
      await userEvent.keyboard('{ArrowRight}');
      await userEvent.keyboard('{ArrowRight}');
      unmount();
      await answer(0, saved('yes'));
      expect(sent(spy)).toEqual(['yes', 'no']);
      await answer(1, saved('no'));

      expect(sent(spy)).toEqual(['yes', 'no']);
      expect(onSaved).not.toHaveBeenCalled();
    });
  });

  describe('a refused save (rules 12 and 13)', () => {
    it.each([
      ['a full event', refused(422, 'This event is full.'), 'This event is full.'],
      ['a cancelled event', refused(422, 'This event is cancelled. It takes no answers.'), 'This event is cancelled. It takes no answers.'],
      [
        'an event without a replay',
        refused(400, 'The provided data is invalid', { answer: ['This event has no replay.'] }),
        'This event has no replay.',
      ],
      ['a failed request', refused(0, 'Failed to save your answer.'), 'Failed to save your answer.'],
    ])('checks the old choice again and says why in an alert, for %s (rule 12)', async (_name, failure, words) => {
      const spy = vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(failure);
      const { onSaved, onGone, container } = mount(open({ my_answer: 'no' }));

      await userEvent.click(radio('Replay only'));

      const alert = await screen.findByRole('alert');
      expect(alert).toHaveTextContent(words);
      expect(spy).toHaveBeenCalledTimes(1);
      // The old choice is back, the status says nothing, and the focus did not leave the group
      expect(checked()).toEqual(['no']);
      expect(screen.getByRole('status')).toBeEmptyDOMElement();
      expect(group().contains(document.activeElement)).toBe(true);
      expect(group()).not.toHaveAttribute('aria-busy');
      // The page gets the event again: the places and the state of the server
      expect(onSaved).toHaveBeenCalledTimes(1);
      expect(onGone).not.toHaveBeenCalled();
      await noViolation(container);
    });

    it('returns to no choice when the first answer is refused, and removes the alert at the next choice (rule 12)', async () => {
      const spy = vi.spyOn(knowiiApiClient.communities.events.answer, 'set');
      spy.mockResolvedValueOnce(refused(422, 'This event is full.')).mockResolvedValueOnce(saved('no'));
      mount(open({ places_left: 1 }));

      await userEvent.click(radio('Yes'));
      expect(await screen.findByRole('alert')).toHaveTextContent('This event is full.');
      expect(checked()).toEqual([]);
      expect(screen.queryByRole('button', { name: 'Clear my answer' })).toBeNull();

      await userEvent.click(radio('No'));
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: No'));
      expect(screen.queryByRole('alert')).toBeNull();
    });

    it('keeps the answer that the server holds when the choice after it is refused (rule 12)', async () => {
      const { spy, answer } = slowSet();
      mount(open());

      await userEvent.click(radio('Yes'));
      await userEvent.keyboard('{ArrowRight}');
      await answer(0, saved('yes'));
      await answer(1, refused(400, 'The provided data is invalid', { answer: ['This event has no replay.'] }));

      expect(sent(spy)).toEqual(['yes', 'replay']);
      // "Yes" was saved: it is the old choice
      expect(checked()).toEqual(['yes']);
      expect(screen.getByRole('alert')).toHaveTextContent('This event has no replay.');
    });

    it.each([
      ['the event is gone (404)', 404],
      ['the reader is no longer a member (403)', 403],
    ])('asks for the whole page again when %s (rule 13)', async (_name, status) => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(refused(status, 'Not for you'));
      const { onSaved, onGone } = mount(open());

      await userEvent.click(radio('Yes'));

      await waitFor(() => expect(onGone).toHaveBeenCalledTimes(1));
      expect(onSaved).not.toHaveBeenCalled();
      expect(checked()).toEqual([]);
    });
  });

  describe('a refusal whose load takes away the element that holds the focus', () => {
    const CANCELLED = 'This event is cancelled. It takes no answers.';
    const NO_REPLAY = refused(400, 'The provided data is invalid', { answer: ['This event has no replay.'] });
    const heading = () => screen.getByRole('heading', { level: 2, name: 'Your answer' });

    it('keeps the alert next to the old answer as text, and gives the focus to the heading, when the event is cancelled', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(refused(422, CANCELLED));
      const { show, onSaved, container } = mount(open({ my_answer: 'no' }));

      await userEvent.click(radio('Yes'));
      expect(await screen.findByRole('alert')).toHaveTextContent(CANCELLED);
      expect(onSaved).toHaveBeenCalledTimes(1);

      // The load after the refusal: the event takes no answers, and the group goes away
      show(open({ state: 'cancelled', can_answer: false, my_answer: 'no' }));

      expect(screen.queryByRole('radiogroup')).toBeNull();
      expect(screen.getByRole('alert')).toHaveTextContent(CANCELLED);
      expect(screen.getByText('Your answer: No')).toBeInTheDocument();
      expect(heading()).toHaveFocus();
      expect(heading()).toHaveAttribute('tabindex', '-1');
      expect(document.body).not.toHaveFocus();
      await noViolation(container);
    });

    it('keeps the alert under the heading, with the focus on the heading, when the member had no answer', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(refused(422, CANCELLED));
      const { show, container } = mount(open());

      await userEvent.click(radio('Yes'));
      expect(await screen.findByRole('alert')).toHaveTextContent(CANCELLED);
      show(open({ state: 'cancelled', can_answer: false }));

      expect(screen.getByRole('alert')).toHaveTextContent(CANCELLED);
      expect(screen.queryByText(/^Your answer:/)).toBeNull();
      expect(screen.queryByRole('radio')).toBeNull();
      expect(heading()).toHaveFocus();
      await noViolation(container);
    });

    it('keeps the alert when the event became a draft: the block shows no answer', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(
        refused(422, 'This event is a draft. It takes no answers.'),
      );
      const { show } = mount(open({ my_answer: 'no' }));

      await userEvent.click(radio('Yes'));
      await screen.findByRole('alert');
      show(open({ state: 'draft', can_answer: false, my_answer: 'no' }));

      expect(screen.getByRole('alert')).toHaveTextContent('This event is a draft. It takes no answers.');
      expect(screen.queryByText(/^Your answer:/)).toBeNull();
      expect(heading()).toHaveFocus();
    });

    it.each([
      ['the answer of the member, checked again', { my_answer: 'no' }, 'No', ['no']],
      ['the first radio, for a member without an answer', {}, 'Yes', []],
    ] as const)(
      'keeps the alert and the focus in the group when "Replay only" goes away with the replay: %s',
      async (_name, changes, focused, answer) => {
        vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(NO_REPLAY);
        const { show, container } = mount(open(changes));

        await userEvent.click(radio('Replay only'));
        expect(await screen.findByRole('alert')).toHaveTextContent('This event has no replay.');
        expect(radio('Replay only')).toHaveFocus();

        // The load after the refusal: the host removed the replay
        show(open({ ...changes, replay_expected: false }));

        expect(within(group()).queryByRole('radio', { name: 'Replay only' })).toBeNull();
        expect(screen.getByRole('alert')).toHaveTextContent('This event has no replay.');
        expect(radio(focused)).toHaveFocus();
        // The focus chose nothing
        expect(checked()).toEqual(answer);
        await noViolation(container);
      },
    );

    it('leaves the focus where the member put it after the refusal', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(refused(422, CANCELLED));
      const { show } = mount(open({ my_answer: 'no' }));

      await userEvent.click(radio('Yes'));
      await screen.findByRole('alert');
      screen.getByRole('button', { name: 'After' }).focus();
      show(open({ state: 'cancelled', can_answer: false, my_answer: 'no' }));

      expect(screen.getByRole('alert')).toHaveTextContent(CANCELLED);
      expect(screen.getByRole('button', { name: 'After' })).toHaveFocus();
    });

    it('takes no focus when the group goes away without a refusal', () => {
      const { show } = mount(open({ my_answer: 'yes' }));

      radio('Yes').focus();
      show(open({ state: 'cancelled', can_answer: false, my_answer: 'yes' }));

      expect(screen.getByText('Your answer: Yes')).toBeInTheDocument();
      expect(screen.queryByRole('alert')).toBeNull();
      expect(heading()).not.toHaveFocus();
    });

    it('removes the alert when the group is back: the event takes answers again', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(refused(422, CANCELLED));
      const { show } = mount(open({ my_answer: 'no' }));

      await userEvent.click(radio('Yes'));
      await screen.findByRole('alert');
      show(open({ state: 'cancelled', can_answer: false, my_answer: 'no' }));
      expect(screen.getByRole('alert')).toBeInTheDocument();

      show(open({ my_answer: 'no' }));
      expect(checked()).toEqual(['no']);
      expect(screen.queryByRole('alert')).toBeNull();
    });
  });

  describe('"Clear my answer" (rule 14)', () => {
    it('shows the button only to a member who has an answer', () => {
      const { show } = mount(open());
      expect(screen.queryByRole('button', { name: 'Clear my answer' })).toBeNull();

      show(open({ my_answer: 'no' }));
      expect(screen.getByRole('button', { name: 'Clear my answer' })).toBeInTheDocument();
    });

    it('clears the answer: no radio is checked, the status says so, the button goes, the focus is on the first radio', async () => {
      const spy = vi.spyOn(knowiiApiClient.communities.events.answer, 'clear').mockResolvedValue(cleared);
      const set = vi.spyOn(knowiiApiClient.communities.events.answer, 'set');
      const { onSaved, container } = mount(open({ my_answer: 'replay' }));

      await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));

      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Answer cleared'));
      expect(spy).toHaveBeenCalledTimes(1);
      expect(spy).toHaveBeenCalledWith('c1', 's1', 'e1');
      expect(set).not.toHaveBeenCalled();
      expect(checked()).toEqual([]);
      expect(screen.queryByRole('button', { name: 'Clear my answer' })).toBeNull();
      expect(radio('Yes')).toHaveFocus();
      expect(onSaved).toHaveBeenCalledTimes(1);
      await noViolation(container);
    });

    it('checks the old answer again when the clear is refused, with the words of the server in an alert', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'clear').mockResolvedValue(
        refused(422, 'This event is over. It takes no answers.'),
      );
      const { onSaved } = mount(open({ my_answer: 'yes' }));

      await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));

      expect(await screen.findByRole('alert')).toHaveTextContent('This event is over. It takes no answers.');
      expect(checked()).toEqual(['yes']);
      expect(screen.getByRole('status')).toBeEmptyDOMElement();
      expect(screen.getByRole('button', { name: 'Clear my answer' })).toBeInTheDocument();
      expect(group().contains(document.activeElement)).toBe(true);
      expect(onSaved).toHaveBeenCalledTimes(1);
    });
  });

  describe('a full event (rules 15 and 16)', () => {
    const full = (changes: Record<string, unknown> = {}) => open({ places_left: 0, ...changes });

    it('marks "Yes" as not available, with the reason as text, for a member whose answer is not Yes (rule 15)', async () => {
      const { container } = mount(full());

      expect(radio('Yes')).toHaveAttribute('aria-disabled', 'true');
      expect(radio('Yes')).not.toBeDisabled();
      // The reason is the places line, a text of the page: never a tooltip
      const line = screen.getByText('Full');
      expect(radio('Yes')).toHaveAttribute('aria-describedby', line.id);
      expect(line).toBeVisible();
      expect(radio('Replay only')).not.toHaveAttribute('aria-disabled');
      expect(radio('No')).not.toHaveAttribute('aria-disabled');
      await noViolation(container);
    });

    it('sends nothing and checks nothing for a click on "Yes" (rule 15)', async () => {
      const spy = vi.spyOn(knowiiApiClient.communities.events.answer, 'set');
      mount(full());

      await userEvent.click(radio('Yes'));

      expect(spy).not.toHaveBeenCalled();
      expect(checked()).toEqual([]);
      expect(group()).not.toHaveAttribute('aria-busy');
    });

    it('sends nothing and checks nothing for Space on "Yes" (rule 15)', async () => {
      const spy = vi.spyOn(knowiiApiClient.communities.events.answer, 'set');
      mount(full());

      screen.getByRole('button', { name: 'Before' }).focus();
      await userEvent.tab();
      expect(radio('Yes')).toHaveFocus();
      await userEvent.keyboard(' ');

      expect(spy).not.toHaveBeenCalled();
      expect(checked()).toEqual([]);
    });

    it('sends nothing and keeps the choice for an arrow key that reaches "Yes" (rule 15)', async () => {
      const spy = vi.spyOn(knowiiApiClient.communities.events.answer, 'set');
      mount(full({ my_answer: 'replay' }));

      radio('Replay only').focus();
      await userEvent.keyboard('{ArrowLeft}');

      expect(spy).not.toHaveBeenCalled();
      expect(checked()).toEqual(['replay']);
    });

    it('still takes "Replay only" and "No" (rule 15)', async () => {
      const spy = vi
        .spyOn(knowiiApiClient.communities.events.answer, 'set')
        .mockImplementation((_c, _s, _e, answer) => Promise.resolve(saved(answer)));
      mount(full());

      await userEvent.click(radio('Replay only'));
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: Replay only'));
      await userEvent.click(radio('No'));
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: No'));

      expect(sent(spy)).toEqual(['replay', 'no']);
    });

    it('keeps "Yes" checked and available for a member whose answer is Yes (rule 16)', () => {
      mount(full({ my_answer: 'yes' }));

      expect(checked()).toEqual(['yes']);
      expect(radio('Yes')).not.toHaveAttribute('aria-disabled');
      expect(screen.getByText('Full')).toBeInTheDocument();
    });
  });

  describe('the question before a member gives up a place on a full event (the decision of 2026-11-20)', () => {
    const TITLE = 'Give up your place?';
    const TEXT = 'This event is full. If you change your answer, another member can take your place.';
    const CANCELLED = 'This event is cancelled. It takes no answers.';
    // A member with a Yes on an event with a limit and no place left
    const taken = (changes: Record<string, unknown> = {}) => open({ places_left: 0, my_answer: 'yes', ...changes });
    const question = () => screen.findByRole('dialog', { name: TITLE });
    const noQuestion = () => waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    const keep = () => screen.getByRole('button', { name: 'Keep my place' });
    const giveUp = () => screen.getByRole('button', { name: 'Give up my place' });
    const spies = () => ({
      set: vi
        .spyOn(knowiiApiClient.communities.events.answer, 'set')
        .mockImplementation((_c, _s, _e, answer) => Promise.resolve(saved(answer))),
      clear: vi.spyOn(knowiiApiClient.communities.events.answer, 'clear').mockResolvedValue(cleared),
    });

    it('asks one question, a dialog with its title as its name and its text as its description, and sends nothing (rules 1, 2 and 6)', async () => {
      const { set, clear } = spies();
      const { onSaving, onSaved } = mount(taken());

      await userEvent.click(radio('No'));

      const dialog = await question();
      expect(screen.getAllByRole('dialog')).toHaveLength(1);
      expect(dialog).toHaveAccessibleName(TITLE);
      expect(dialog).toHaveAccessibleDescription(TEXT);
      expect(within(dialog).getByRole('heading', { name: TITLE })).toBeInTheDocument();
      // Two choices, and the cross of every dialog
      expect(
        within(dialog)
          .getAllByRole('button')
          .map((button) => button.textContent || button.getAttribute('aria-label')),
      ).toEqual(['Close', 'Keep my place', 'Give up my place']);
      // The safe button has the focus: Enter and Space keep the place
      await waitFor(() => expect(keep()).toHaveFocus());
      // Nothing is sent, and nothing says "Saving"
      expect(set).not.toHaveBeenCalled();
      expect(clear).not.toHaveBeenCalled();
      expect(onSaving).not.toHaveBeenCalled();
      expect(onSaved).not.toHaveBeenCalled();
      expect(group()).not.toHaveAttribute('aria-busy');
      expect(screen.getByRole('status')).toBeEmptyDOMElement();
      // The dialog is outside the block, at the end of the document
      await noViolation(document.body);
    });

    it.each([
      ['Replay only', 'replay', 'Saved: Replay only'],
      ['No', 'no', 'Saved: No'],
    ] as const)('saves "%s" after "Give up my place", like every other choice (rule 3)', async (name, value, status) => {
      const { set, clear } = spies();
      const { onSaving, onSaved, onGone } = mount(taken());

      await userEvent.click(radio(name));
      await question();
      expect(set).not.toHaveBeenCalled();

      await userEvent.click(giveUp());

      expect(set).toHaveBeenCalledTimes(1);
      expect(set).toHaveBeenCalledWith('c1', 's1', 'e1', value);
      expect(clear).not.toHaveBeenCalled();
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent(status));
      expect(checked()).toEqual([value]);
      expect(onSaving.mock.calls).toEqual([[true], [false]]);
      // The page gets the event again, one time
      expect(onSaved).toHaveBeenCalledTimes(1);
      expect(onGone).not.toHaveBeenCalled();
      await noQuestion();
      await waitFor(() => expect(radio(name)).toHaveFocus());
    });

    it('asks for "Clear my answer" on a full event, and clears only after "Give up my place" (rules 1 and 3)', async () => {
      const { set, clear } = spies();
      const { onSaved } = mount(taken());

      await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));
      await question();
      expect(clear).not.toHaveBeenCalled();
      expect(set).not.toHaveBeenCalled();

      await userEvent.click(giveUp());

      expect(clear).toHaveBeenCalledTimes(1);
      expect(clear).toHaveBeenCalledWith('c1', 's1', 'e1');
      expect(set).not.toHaveBeenCalled();
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Answer cleared'));
      expect(checked()).toEqual([]);
      expect(screen.queryByRole('button', { name: 'Clear my answer' })).toBeNull();
      expect(onSaved).toHaveBeenCalledTimes(1);
      await noQuestion();
      // The button went away with the answer: the first radio takes the focus, as after every clear
      await waitFor(() => expect(radio('Yes')).toHaveFocus());
    });

    it.each([
      ['"Keep my place"', async () => userEvent.click(keep())],
      ['Escape', async () => userEvent.keyboard('{Escape}')],
      ['a click outside', async () => userEvent.click(document.querySelector('[class*="bg-black/70"]') as HTMLElement)],
      ['the cross of the dialog', async () => userEvent.click(screen.getByRole('button', { name: 'Close' }))],
    ])('sends nothing with %s: "Yes" is checked again and takes the focus (rule 3)', async (_name, close) => {
      const { set, clear } = spies();
      const { onSaving, onSaved, onGone, container } = mount(taken());

      await userEvent.click(radio('No'));
      await question();
      await waitFor(() => expect(keep()).toHaveFocus());
      // The choice shows behind the question
      expect(checked()).toEqual(['no']);

      await close();

      await noQuestion();
      expect(checked()).toEqual(['yes']);
      await waitFor(() => expect(radio('Yes')).toHaveFocus());
      expect(set).not.toHaveBeenCalled();
      expect(clear).not.toHaveBeenCalled();
      expect(onSaving).not.toHaveBeenCalled();
      expect(onSaved).not.toHaveBeenCalled();
      expect(onGone).not.toHaveBeenCalled();
      expect(screen.getByRole('status')).toBeEmptyDOMElement();
      expect(screen.queryByRole('alert')).toBeNull();
      expect(screen.getByRole('button', { name: 'Clear my answer' })).toBeInTheDocument();
      await noViolation(container);
    });

    it('keeps the answer after "Clear my answer" and "Keep my place": the button is back, "Yes" has the focus (rule 3)', async () => {
      const { set, clear } = spies();
      mount(taken());

      await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));
      await question();
      await userEvent.click(keep());

      await noQuestion();
      expect(checked()).toEqual(['yes']);
      await waitFor(() => expect(radio('Yes')).toHaveFocus());
      expect(screen.getByRole('button', { name: 'Clear my answer' })).toBeInTheDocument();
      expect(clear).not.toHaveBeenCalled();
      expect(set).not.toHaveBeenCalled();
    });

    it('asks one time for an arrow key on "Yes", takes no second key, and leaves the group as before after "Keep my place" (rule 4)', async () => {
      const { set } = spies();
      const { container } = mount(taken());

      screen.getByRole('button', { name: 'Before' }).focus();
      await userEvent.tab();
      expect(radio('Yes')).toHaveFocus();
      await userEvent.keyboard('{ArrowRight}');

      // The choice moved to the next radio, and the question is open
      await question();
      expect(screen.getAllByRole('dialog')).toHaveLength(1);
      expect(checked()).toEqual(['replay']);
      await waitFor(() => expect(keep()).toHaveFocus());

      // A second arrow key goes to the dialog: the choice does not move
      await userEvent.keyboard('{ArrowRight}');
      expect(checked()).toEqual(['replay']);
      expect(screen.getAllByRole('dialog')).toHaveLength(1);
      // A change that still reaches the group (a key before the dialog took the focus) changes nothing. The page behind
      // the question is hidden from the roles
      fireEvent.click(container.querySelector('input[type="radio"][value="no"]') as HTMLInputElement);
      expect(checked()).toEqual(['replay']);
      expect(screen.getAllByRole('dialog')).toHaveLength(1);
      expect(set).not.toHaveBeenCalled();

      await userEvent.click(keep());

      await noQuestion();
      expect(checked()).toEqual(['yes']);
      await waitFor(() => expect(radio('Yes')).toHaveFocus());
      expect(set).not.toHaveBeenCalled();
      // One tab stop, on "Yes": Shift+Tab leaves the group
      await userEvent.tab({ shift: true });
      expect(screen.getByRole('button', { name: 'Before' })).toHaveFocus();

      // The next key asks again, one time, and the choice that is saved is the one of that key
      await userEvent.tab();
      await userEvent.keyboard('{ArrowRight}');
      await question();
      await userEvent.click(giveUp());
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: Replay only'));
      expect(sent(set)).toEqual(['replay']);
    });

    it('asks no second question while the save of "Give up my place" runs: the next choice waits for it (rule 9)', async () => {
      const { spy, answer } = slowSet();
      mount(taken());

      await userEvent.click(radio('No'));
      await question();
      await userEvent.click(giveUp());
      await noQuestion();
      await waitFor(() => expect(radio('No')).toHaveFocus());
      expect(sent(spy)).toEqual(['no']);

      await userEvent.keyboard('{ArrowLeft}');
      expect(screen.queryByRole('dialog')).toBeNull();
      expect(sent(spy)).toEqual(['no']);

      await answer(0, saved('no'));
      expect(sent(spy)).toEqual(['no', 'replay']);
      await answer(1, saved('replay'));
      expect(screen.getByRole('status')).toHaveTextContent('Saved: Replay only');
    });

    it('shows the alert of a refusal after "Give up my place", with the old choice and the focus in the group (rule 3)', async () => {
      const set = vi
        .spyOn(knowiiApiClient.communities.events.answer, 'set')
        .mockResolvedValue(refused(400, 'The provided data is invalid', { answer: ['This event has no replay.'] }));
      const { onSaved, onGone } = mount(taken());

      await userEvent.click(radio('Replay only'));
      await question();
      await userEvent.click(giveUp());

      expect(await screen.findByRole('alert')).toHaveTextContent('This event has no replay.');
      expect(set).toHaveBeenCalledTimes(1);
      expect(checked()).toEqual(['yes']);
      expect(screen.getByRole('status')).toBeEmptyDOMElement();
      expect(onSaved).toHaveBeenCalledTimes(1);
      expect(onGone).not.toHaveBeenCalled();
      await noQuestion();
      await waitFor(() => expect(radio('Yes')).toHaveFocus());
    });

    it('loads the whole page when the event is no longer for the member after "Give up my place" (rule 13)', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(refused(404, 'Not found'));
      const { onSaved, onGone } = mount(taken());

      await userEvent.click(radio('No'));
      await question();
      await userEvent.click(giveUp());

      await waitFor(() => expect(onGone).toHaveBeenCalledTimes(1));
      expect(onSaved).not.toHaveBeenCalled();
      expect(screen.queryByRole('alert')).toBeNull();
    });

    describe('the event changes while the question is open (rule 5)', () => {
      it('keeps the question when a place frees up, sends nothing by itself, and both buttons work', async () => {
        const { set, clear } = spies();
        const { show, onSaved } = mount(taken());

        await userEvent.click(radio('No'));
        const dialog = await question();
        // A live update: another member left, the event is not full any more
        show(taken({ places_left: 1 }));

        expect(screen.getByRole('dialog', { name: TITLE })).toBe(dialog);
        expect(checked()).toEqual(['no']);
        expect(set).not.toHaveBeenCalled();
        expect(clear).not.toHaveBeenCalled();

        await userEvent.click(keep());
        await noQuestion();
        expect(checked()).toEqual(['yes']);
        await waitFor(() => expect(radio('Yes')).toHaveFocus());
        expect(set).not.toHaveBeenCalled();

        // The event is not full: the same choice now saves at once
        await userEvent.click(radio('No'));
        expect(screen.queryByRole('dialog')).toBeNull();
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: No'));
        expect(sent(set)).toEqual(['no']);
        expect(onSaved).toHaveBeenCalledTimes(1);
      });

      it('saves the choice after "Give up my place" when a place freed up meanwhile', async () => {
        const { set } = spies();
        const { show } = mount(taken());

        await userEvent.click(radio('No'));
        await question();
        show(taken({ places_left: 1 }));
        expect(set).not.toHaveBeenCalled();

        await userEvent.click(giveUp());

        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: No'));
        expect(sent(set)).toEqual(['no']);
      });

      it('keeps the question when the event is cancelled, sends nothing by itself, then shows the refusal of the server', async () => {
        const set = vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue(refused(422, CANCELLED));
        const { show, onSaved } = mount(taken());

        await userEvent.click(radio('No'));
        const dialog = await question();
        await waitFor(() => expect(keep()).toHaveFocus());
        // A live update: the host cancelled the event, and the group goes away behind the question
        show(taken({ state: 'cancelled', can_answer: false }));

        // The same question, not a new one: the focus of the member stays in it
        expect(screen.getByRole('dialog', { name: TITLE })).toBe(dialog);
        expect(keep()).toHaveFocus();
        expect(screen.queryByRole('radiogroup')).toBeNull();
        expect(set).not.toHaveBeenCalled();

        await userEvent.click(giveUp());

        // The save ran against the event as it is, and the server refused it
        expect(set).toHaveBeenCalledTimes(1);
        expect(set).toHaveBeenCalledWith('c1', 's1', 'e1', 'no');
        expect(await screen.findByRole('alert')).toHaveTextContent(CANCELLED);
        expect(screen.getByText('Your answer: Yes')).toBeInTheDocument();
        expect(onSaved).toHaveBeenCalledTimes(1);
        await noQuestion();
        // The group is gone: the heading of the block takes the focus
        await waitFor(() => expect(screen.getByRole('heading', { level: 2, name: 'Your answer' })).toHaveFocus());
      });

      it('sends nothing for "Keep my place" when the event is cancelled meanwhile', async () => {
        const { set, clear } = spies();
        const { show, onSaved } = mount(taken());

        await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));
        await question();
        show(taken({ state: 'cancelled', can_answer: false }));
        await userEvent.click(keep());

        await noQuestion();
        expect(set).not.toHaveBeenCalled();
        expect(clear).not.toHaveBeenCalled();
        expect(onSaved).not.toHaveBeenCalled();
        expect(screen.getByText('Your answer: Yes')).toBeInTheDocument();
        await waitFor(() => expect(screen.getByRole('heading', { level: 2, name: 'Your answer' })).toHaveFocus());
      });
    });

    describe('every other case: no question, the save at once (rule 1)', () => {
      it.each([
        ['an event that is not full', { places_left: 3, my_answer: 'yes' }],
        ['an event without a limit', { places_left: null, my_answer: 'yes' }],
        ['a member of a full event whose answer is "Replay only"', { places_left: 0, my_answer: 'replay' }],
        ['a member of a full event without an answer', { places_left: 0, my_answer: null }],
      ])('saves "No" at once for %s', async (_name, changes) => {
        const { set } = spies();
        mount(open(changes));

        await userEvent.click(radio('No'));

        expect(set).toHaveBeenCalledTimes(1);
        expect(set).toHaveBeenCalledWith('c1', 's1', 'e1', 'no');
        expect(screen.queryByRole('dialog')).toBeNull();
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: No'));
      });

      it.each([
        ['an event that is not full', { places_left: 3, my_answer: 'yes' }],
        ['an event without a limit', { places_left: null, my_answer: 'yes' }],
        ['a member of a full event whose answer is "No"', { places_left: 0, my_answer: 'no' }],
      ])('clears the answer at once for %s', async (_name, changes) => {
        const { clear } = spies();
        mount(open(changes));

        await userEvent.click(screen.getByRole('button', { name: 'Clear my answer' }));

        expect(clear).toHaveBeenCalledTimes(1);
        expect(screen.queryByRole('dialog')).toBeNull();
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Answer cleared'));
      });

      it('saves an arrow key at once on an event that is not full', async () => {
        const { set } = spies();
        mount(open({ places_left: 1, my_answer: 'yes' }));

        radio('Yes').focus();
        await userEvent.keyboard('{ArrowRight}');

        expect(sent(set)).toEqual(['replay']);
        expect(screen.queryByRole('dialog')).toBeNull();
      });

      it('asks nothing and sends nothing for "Yes" again on a full event', async () => {
        const { set } = spies();
        mount(taken());

        await userEvent.click(radio('Yes'));

        expect(set).not.toHaveBeenCalled();
        expect(screen.queryByRole('dialog')).toBeNull();
      });
    });
  });

  describe('an event that takes no answers (rules 17 and 18)', () => {
    it.each([
      ['a cancelled event', { state: 'cancelled', can_answer: false }, EVENT_TEST_NOW, 'yes', 'Your answer: Yes'],
      ['an event that ended', { is_past: true, can_answer: false }, EVENT_TEST_NOW, 'replay', 'Your answer: Replay only'],
      // The page was open when the event ended: the server still said that it takes answers
      ['an event whose end passed on the open page', {}, new Date('2026-11-18T17:00:00Z'), 'no', 'Your answer: No'],
    ] as const)(
      'shows the old answer as text, with no radio, no button and no places line, for %s (rule 17)',
      async (_n, changes, now, answer, words) => {
        const { container } = mount(open({ ...changes, my_answer: answer, places_left: 5 }), 'member', now);

        expect(screen.getByRole('heading', { level: 2, name: 'Your answer' })).toBeInTheDocument();
        expect(screen.getByText(words)).toBeInTheDocument();
        expect(screen.queryByRole('radiogroup')).toBeNull();
        expect(screen.queryByRole('radio')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Clear my answer' })).toBeNull();
        expect(screen.queryByText('5 places left')).toBeNull();
        expect(screen.queryByText(PRIVACY)).toBeNull();
        await noViolation(container);
      },
    );

    it.each([
      ['a member without an answer on a cancelled event', { state: 'cancelled', can_answer: false }],
      ['a member without an answer on an event that ended', { is_past: true, can_answer: false }],
      ['the host of a draft', { state: 'draft', can_answer: false }],
      ['a draft that holds an old answer', { state: 'draft', can_answer: false, my_answer: 'yes' }],
    ])('renders nothing for %s (rule 18)', (_name, changes) => {
      const { container } = mount(open(changes));

      expect(container.querySelector('[data-slot="event-answer"]')).toBeNull();
      expect(screen.queryByText('Your answer')).toBeNull();
    });
  });

  describe('a visitor and a person who is not a member (rules 19 and 20)', () => {
    const forOthers = { my_answer: null, can_answer: false, places_left: null };

    it('gives a visitor the heading and "Sign in to answer" on a published event that is not over (rule 19)', async () => {
      const { container } = mount(open(forOthers), 'visitor');

      expect(screen.getByRole('heading', { level: 2, name: 'Your answer' })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Sign in to answer' })).toHaveAttribute('href', '/login');
      expect(screen.queryByRole('radio')).toBeNull();
      await noViolation(container);
    });

    it.each([
      ['a cancelled event', { state: 'cancelled' }, EVENT_TEST_NOW],
      ['a past event', { is_past: true }, EVENT_TEST_NOW],
      ['an event whose end passed on the open page', {}, new Date('2026-11-18T17:00:00Z')],
      ['a draft', { state: 'draft' }, EVENT_TEST_NOW],
    ])('gives a visitor nothing on %s (rule 19)', (_name, changes, now) => {
      mount(open({ ...forOthers, ...changes }), 'visitor', now);

      expect(screen.queryByText('Your answer')).toBeNull();
      expect(screen.queryByRole('link', { name: 'Sign in to answer' })).toBeNull();
    });

    it('gives a signed-in person who is not a member nothing (rule 20)', () => {
      mount(open(forOthers), 'outsider');

      expect(screen.queryByText('Your answer')).toBeNull();
      expect(screen.queryByRole('link')).toBeNull();
      expect(screen.queryByRole('radio')).toBeNull();
    });
  });

  it.each(['member', 'outsider', 'visitor'] as const)('sends no request when it renders, for a %s (rules 21 and 61)', (reader) => {
    const calls = [
      vi.spyOn(knowiiApiClient.communities.events.answer, 'get'),
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set'),
      vi.spyOn(knowiiApiClient.communities.events.answer, 'clear'),
      vi.spyOn(knowiiApiClient.communities.events.answers, 'list'),
    ];

    mount(open(reader === 'member' ? { my_answer: 'yes' } : { my_answer: null, can_answer: false, places_left: null }), reader);

    for (const call of calls) {
      expect(call).not.toHaveBeenCalled();
    }
  });

  describe('the places (rules 22 to 25)', () => {
    it.each([
      [28, '28 places left'],
      [1, '1 place left'],
      [0, 'Full'],
    ])('says the places that are left under the group: %i (rule 22)', (places, words) => {
      mount(open({ places_left: places }));

      const line = screen.getByText(words);
      expect(line.tagName).toBe('P');
      // After the group, in the page
      expect(group().compareDocumentPosition(line) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('writes every number in the color of secondary text, with no icon and no word of urgency (rule 23)', () => {
      const { show } = mount(open({ places_left: 28 }));
      const classes = screen.getByText('28 places left').className;
      expect(classes).toContain('text-muted');

      for (const [places, words] of [
        [2, '2 places left'],
        [1, '1 place left'],
        [0, 'Full'],
      ] as const) {
        show(open({ places_left: places }));
        const line = screen.getByText(words);
        expect(line.className).toBe(classes);
        expect(line.querySelector('svg')).toBeNull();
        expect(line.textContent).toBe(words);
      }
    });

    it('shows no line and no count without a limit (rule 24)', () => {
      const { container } = mount(open({ places_left: null }));

      expect(screen.queryByText(/places? left/)).toBeNull();
      expect(screen.queryByText('Full')).toBeNull();
      expect(container.querySelector('[data-slot="event-places"]')).toBeNull();
    });

    it('never shows the limit, also to a reader who gets it (rule 25)', () => {
      const { container } = mount(open({ places_left: 28, capacity: 40 }));

      expect(container.textContent).not.toContain('40');
    });
  });

  describe('the keyboard and the automated checks (rules 62 and 63)', () => {
    it('reaches the group one time, moves the choice with the arrow keys, then reaches "Clear my answer"', async () => {
      const spy = vi
        .spyOn(knowiiApiClient.communities.events.answer, 'set')
        .mockImplementation((_c, _s, _e, answer) => Promise.resolve(saved(answer)));
      mount(open({ my_answer: 'yes' }));

      screen.getByRole('button', { name: 'Before' }).focus();
      await userEvent.tab();
      expect(radio('Yes')).toHaveFocus();
      await userEvent.keyboard('{ArrowRight}');
      expect(radio('Replay only')).toHaveFocus();
      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: Replay only'));
      expect(sent(spy)).toEqual(['replay']);

      await userEvent.tab();
      expect(screen.getByRole('button', { name: 'Clear my answer' })).toHaveFocus();
      await userEvent.tab();
      expect(screen.getByRole('button', { name: 'After' })).toHaveFocus();
    });

    it.each([
      ['a member who did not answer', {}],
      ['a member who answered Yes', { my_answer: 'yes', places_left: 27 }],
      ['a member of an event without a limit and without a replay', { replay_expected: false, places_left: null, my_answer: 'no' }],
    ])('has no automated violation for %s (axe)', async (_name, changes) => {
      const { container } = mount(open(changes));

      await noViolation(container);
    });

    it('has no automated violation while a save runs and after it (axe)', async () => {
      const { answer } = slowSet();
      const { container } = mount(open());

      await userEvent.click(radio('Yes'));
      await noViolation(container);
      await answer(0, saved('yes'));
      await noViolation(container);
    });
  });
});
