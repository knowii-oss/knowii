import { useState } from 'react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { ApiResult, EventAnswerCounts, EventAnswerMember, Paginated } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { emitSocketEvent } from '@/test-utils/fake-echo';
import { eventFixture, eventsCommunity, eventsRoute, eventsSpace } from '@/test-utils/events';
import { EventAnswersDialog } from './EventAnswersDialog';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
  // jsdom has no scrollIntoView: the first new name scrolls into view after a load
  Element.prototype.scrollIntoView = vi.fn();
});

type ListResult = ApiResult<Paginated<EventAnswerMember> & { counts: EventAnswerCounts }>;

const DESCRIPTION = 'The host, the owner and the admins see this list. Other members do not.';
const member = (index: number, prefix = 'Member'): EventAnswerMember => ({
  cuid: `${prefix.toLowerCase()}-${index}`,
  name: `${prefix} ${String(index).padStart(2, '0')}`,
  username: `${prefix.toLowerCase()}${index}`,
  avatar_url: null,
});
// 45 members who answered Yes: three loads of 20, 20 and 5
const YES = Array.from({ length: 45 }, (_unused, index) => member(index + 1));
const REPLAY = Array.from({ length: 3 }, (_unused, index) => member(index + 1, 'Later'));

function page(all: EventAnswerMember[], offset: number, counts: EventAnswerCounts): Extract<ListResult, { ok: true }> {
  const items = all.slice(offset, offset + 20);
  const next = offset + 20 < all.length ? offset + 20 : null;
  return {
    ok: true,
    message: 'Answers',
    data: {
      items,
      counts,
      pagination: {
        limit: 20,
        offset,
        previousOffset: offset === 0 ? null : offset - 20,
        nextOffset: next,
        currentPage: offset / 20 + 1,
        pageCount: Math.max(1, Math.ceil(all.length / 20)),
        totalCount: all.length,
      },
    },
  };
}

/**
 * The route of the list, with the members of each answer
 */
function serve(yes: EventAnswerMember[], replay: EventAnswerMember[], no: number) {
  const counts = { yes: yes.length, replay: replay.length, no };
  return vi
    .spyOn(knowiiApiClient.communities.events.answers, 'list')
    .mockImplementation((_c, _s, _e, answer = 'yes', _limit = 20, offset = 0) =>
      Promise.resolve(page(answer === 'yes' ? yes : replay, offset, counts)),
    );
}

interface Options {
  capacity?: number | null;
  replayExpected?: boolean;
  onGone?: () => void;
}

function Opener({ capacity = 40, replayExpected = true, onGone = () => undefined }: Options) {
  const [open, setOpen] = useState(false);
  return (
    <main>
      <button type="button" onClick={() => setOpen(true)}>
        Answers
      </button>
      <EventAnswersDialog
        open={open}
        onClose={() => setOpen(false)}
        community={eventsCommunity}
        space={eventsSpace}
        event={eventFixture('e1', 'Weekly review', { capacity, replay_expected: replayExpected })}
        onGone={onGone}
      />
    </main>
  );
}

async function openDialog(options: Options = {}) {
  render(<Opener {...options} />);
  await userEvent.click(screen.getByRole('button', { name: 'Answers' }));
  return screen.findByRole('dialog', { name: 'Answers' });
}

const names = (dialog: HTMLElement) =>
  within(dialog)
    .queryAllByRole('listitem')
    .map((row) => within(row).getByRole('link').textContent);
const noViolation = async () => {
  const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
  expect(results.violations.map((violation) => violation.id)).toEqual([]);
};

describe('"Answers", the list for the host, the owner and the admins (#1515, design 6.4 point 3)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('sends no request while the dialog is closed', () => {
    const list = serve(YES, REPLAY, 4);

    render(<Opener />);

    expect(list).not.toHaveBeenCalled();
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('opens with its title and says who sees the list; Escape closes it and the focus returns to the button (rule 35)', async () => {
    serve(YES, REPLAY, 4);
    const dialog = await openDialog();

    expect(dialog).toHaveAccessibleDescription(DESCRIPTION);
    await within(dialog).findByRole('list', { name: 'Members who answered Yes' });

    await userEvent.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    await waitFor(() => expect(screen.getByRole('button', { name: 'Answers' })).toHaveFocus());
  });

  it('loads the first 20 Yes answers when it opens, and again each time it opens (rule 36)', async () => {
    const list = serve(YES, REPLAY, 4);
    const dialog = await openDialog();
    await within(dialog).findByRole('list');
    expect(list).toHaveBeenCalledTimes(1);
    expect(list).toHaveBeenCalledWith('c1', 's1', 'e1', 'yes', 20, 0);

    // The second opening starts on "Yes" again, with a new load
    await userEvent.click(within(dialog).getByRole('radio', { name: 'Replay only (3)' }));
    await within(dialog).findByRole('list', { name: 'Members who answered Replay only' });
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    list.mockClear();
    await userEvent.click(screen.getByRole('button', { name: 'Answers' }));
    const again = await screen.findByRole('dialog', { name: 'Answers' });
    await within(again).findByRole('list', { name: 'Members who answered Yes' });

    expect(list).toHaveBeenCalledTimes(1);
    expect(list).toHaveBeenCalledWith('c1', 's1', 'e1', 'yes', 20, 0);
  });

  it('shows lines that hold the place of the names while they load, next to a busy list, never inside it (rule 36)', async () => {
    let answer: (result: ListResult) => void = () => undefined;
    vi.spyOn(knowiiApiClient.communities.events.answers, 'list').mockImplementation(
      () =>
        new Promise((resolve) => {
          answer = resolve;
        }),
    );
    const dialog = await openDialog();

    // The label of the skeleton, in its own polite status
    const loading = within(dialog).getByText('Loading the answers');
    expect(loading.closest('[role="status"]')).not.toBeNull();
    const list = dialog.querySelector('[data-slot="event-answers-list"]');
    expect(list).toHaveAttribute('aria-busy', 'true');
    expect(list?.contains(loading)).toBe(false);
    await noViolation();

    await act(async () => answer(page(YES, 0, { yes: 45, replay: 3, no: 4 })));

    expect(list).toHaveAttribute('aria-busy', 'false');
    expect(within(dialog).queryByText('Loading the answers')).toBeNull();
    expect(names(dialog)).toHaveLength(20);
  });

  it.each([
    ['says the places that are taken, of the limit', 40, '12 of 40 places taken'],
    ['says no places line without a limit', null, null],
  ])('%s (rule 36)', async (_name, capacity, words) => {
    serve(YES.slice(0, 12), REPLAY, 4);
    const dialog = await openDialog({ capacity });
    await within(dialog).findByRole('list');

    if (words === null) {
      expect(within(dialog).queryByText(/places taken/)).toBeNull();
    } else {
      expect(within(dialog).getByText(words)).toBeInTheDocument();
    }
  });

  it('offers "Yes" and "Replay only" with their numbers, and loads the first names of the chosen answer (rule 37)', async () => {
    const list = serve(YES, REPLAY, 4);
    const dialog = await openDialog();
    await within(dialog).findByRole('list');

    const filter = within(dialog).getByRole('radiogroup', { name: 'Answers to show' });
    expect(within(filter).getByRole('radio', { name: 'Yes (45)' })).toBeChecked();
    const replay = within(filter).getByRole('radio', { name: 'Replay only (3)' });
    expect(within(filter).getAllByRole('radio')).toHaveLength(2);

    await userEvent.click(replay);

    await within(dialog).findByRole('list', { name: 'Members who answered Replay only' });
    expect(list).toHaveBeenLastCalledWith('c1', 's1', 'e1', 'replay', 20, 0);
    expect(names(dialog)).toEqual(['Later 01', 'Later 02', 'Later 03']);
    // The choice keeps the focus, and three names need no "Load more"
    expect(replay).toHaveFocus();
    expect(within(dialog).queryByRole('button', { name: 'Load more' })).toBeNull();
    await noViolation();
  });

  it.each([
    ['shows no choice without a replay and without a "Replay only" answer', false, [] as EventAnswerMember[], false],
    ['shows the choice without a replay when a member still answers "Replay only"', false, REPLAY, true],
    ['shows the choice with a replay, also without a "Replay only" answer', true, [] as EventAnswerMember[], true],
  ])('%s (rule 37)', async (_name, replayExpected, replay, shown) => {
    serve(YES.slice(0, 2), replay, 0);
    const dialog = await openDialog({ replayExpected });
    await within(dialog).findByRole('list');

    expect(within(dialog).queryByRole('radiogroup', { name: 'Answers to show' }) !== null).toBe(shown);
  });

  it('lists the members in a real list: the avatar, the name as a link to the member page, and the username (rule 38)', async () => {
    serve([{ cuid: 'u2', name: 'Bea Bell', username: 'bea', avatar_url: null }], [], 0);
    const dialog = await openDialog();

    const list = await within(dialog).findByRole('list', { name: 'Members who answered Yes' });
    expect(list.tagName).toBe('UL');
    const row = within(list).getByRole('listitem');
    expect(within(row).getByRole('link', { name: 'Bea Bell' })).toHaveAttribute('href', '/community/tests/member/bea');
    expect(row).toHaveTextContent('@bea');
    // The initials of the avatar
    expect(row).toHaveTextContent('BB');
  });

  it('loads 45 members in three parts: the focus goes to the first new name and a status says how many loaded (rule 39)', async () => {
    const list = serve(YES, REPLAY, 4);
    const dialog = await openDialog();
    await within(dialog).findByRole('list');
    expect(names(dialog)).toHaveLength(20);

    await userEvent.click(within(dialog).getByRole('button', { name: 'Load more' }));

    await waitFor(() => expect(names(dialog)).toHaveLength(40));
    expect(list).toHaveBeenLastCalledWith('c1', 's1', 'e1', 'yes', 20, 20);
    expect(within(dialog).getByRole('link', { name: 'Member 21' })).toHaveFocus();
    expect(within(dialog).getByText('20 more loaded')).toHaveAttribute('role', 'status');

    await userEvent.click(within(dialog).getByRole('button', { name: 'Load more' }));

    await waitFor(() => expect(names(dialog)).toHaveLength(45));
    expect(list).toHaveBeenLastCalledWith('c1', 's1', 'e1', 'yes', 20, 40);
    expect(within(dialog).getByRole('link', { name: 'Member 41' })).toHaveFocus();
    expect(within(dialog).getByText('5 more loaded')).toBeInTheDocument();
    // The last part: no more button
    expect(within(dialog).queryByRole('button', { name: 'Load more' })).toBeNull();
    expect(names(dialog)[0]).toBe('Member 01');
    expect(names(dialog)[44]).toBe('Member 45');
    expect(list).toHaveBeenCalledTimes(3);
    await noViolation();
  });

  it('never shows a member two times when the list moved between two loads (rule 39)', async () => {
    const counts = { yes: 21, replay: 0, no: 0 };
    vi.spyOn(knowiiApiClient.communities.events.answers, 'list')
      .mockResolvedValueOnce(page(YES.slice(0, 21), 0, counts))
      // A member left: the second part starts with a name that the first part already gave
      .mockResolvedValueOnce(page([YES[19], YES[20]] as EventAnswerMember[], 0, counts));
    const dialog = await openDialog();
    await within(dialog).findByRole('list');

    await userEvent.click(within(dialog).getByRole('button', { name: 'Load more' }));

    await waitFor(() => expect(names(dialog)).toHaveLength(21));
    expect(within(dialog).getByText('1 more loaded')).toBeInTheDocument();
    expect(within(dialog).getByRole('link', { name: 'Member 21' })).toHaveFocus();
  });

  it.each([
    [4, '4 members answered No.'],
    [1, '1 member answered No.'],
    [0, 'No member answered No.'],
  ])('says the number of "No" answers under the list, without a name: %i (rule 40)', async (no, words) => {
    serve(YES.slice(0, 2), REPLAY, no);
    const dialog = await openDialog();
    const list = await within(dialog).findByRole('list');

    const line = within(dialog).getByText(words);
    expect(list.compareDocumentPosition(line) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    // No list and no choice names the members who answered No
    expect(within(dialog).queryByRole('radio', { name: /^No/ })).toBeNull();
    expect(within(dialog).getAllByRole('list')).toHaveLength(1);
  });

  it('shows an empty state for each answer that nobody gave, and still the number of "No" (rule 41)', async () => {
    serve([], [], 2);
    const dialog = await openDialog({ capacity: null });

    expect(await within(dialog).findByText('Nobody answered Yes yet')).toBeInTheDocument();
    expect(within(dialog).queryByRole('list')).toBeNull();
    expect(within(dialog).getByText('2 members answered No.')).toBeInTheDocument();
    await noViolation();

    await userEvent.click(within(dialog).getByRole('radio', { name: 'Replay only (0)' }));
    expect(await within(dialog).findByText('Nobody answered Replay only yet')).toBeInTheDocument();
  });

  it('shows the words of the server in an alert when the load fails, and no empty state (rule 42)', async () => {
    vi.spyOn(knowiiApiClient.communities.events.answers, 'list').mockResolvedValue({
      ok: false,
      status: 500,
      message: 'Failed to load the answers.',
      errors: {},
    });
    const onGone = vi.fn();
    const dialog = await openDialog({ onGone });

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('Failed to load the answers.');
    expect(within(dialog).queryByText('Nobody answered Yes yet')).toBeNull();
    expect(within(dialog).queryByText('Loading the answers')).toBeNull();
    expect(onGone).not.toHaveBeenCalled();
    expect(screen.getByRole('dialog', { name: 'Answers' })).toBeInTheDocument();
    await noViolation();
  });

  it('keeps the names and says the error when a later part fails (rule 42)', async () => {
    const counts = { yes: 45, replay: 0, no: 0 };
    vi.spyOn(knowiiApiClient.communities.events.answers, 'list')
      .mockResolvedValueOnce(page(YES, 0, counts))
      .mockResolvedValueOnce({ ok: false, status: 429, message: 'Too many requests.', errors: {} });
    const dialog = await openDialog();
    await within(dialog).findByRole('list');

    await userEvent.click(within(dialog).getByRole('button', { name: 'Load more' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('Too many requests.');
    expect(names(dialog)).toHaveLength(20);
    const more = within(dialog).getByRole('button', { name: 'Load more' });
    expect(more).not.toHaveAttribute('aria-busy', 'true');
    expect(more).toHaveFocus();
  });

  it.each([403, 404])('closes and tells the page when the list answers %i (rule 42)', async (status) => {
    vi.spyOn(knowiiApiClient.communities.events.answers, 'list').mockResolvedValue({
      ok: false,
      status,
      message: 'Not for you',
      errors: {},
    });
    const onGone = vi.fn();
    render(<Opener onGone={onGone} />);

    await userEvent.click(screen.getByRole('button', { name: 'Answers' }));

    await waitFor(() => expect(onGone).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
  });

  it('has no export, no copy, no search and no time of an answer (rule 43)', async () => {
    serve(YES, REPLAY, 4);
    const dialog = await openDialog();
    await within(dialog).findByRole('list');

    // The buttons of the dialog: its close button and "Load more"
    expect(
      within(dialog)
        .getAllByRole('button')
        .map((button) => button.getAttribute('aria-label') ?? button.textContent),
    ).toEqual(['Close', 'Load more']);
    expect(within(dialog).queryByRole('textbox')).toBeNull();
    expect(within(dialog).queryByRole('searchbox')).toBeNull();
    expect(dialog.querySelector('time')).toBeNull();
    expect(dialog.querySelector('a[download]')).toBeNull();
  });

  it('does not load again on the live signal: the list is the one of the opening (rule 44)', async () => {
    const list = serve(YES, REPLAY, 4);
    const dialog = await openDialog();
    await within(dialog).findByRole('list');
    expect(list).toHaveBeenCalledTimes(1);

    act(() =>
      emitSocketEvent('community.c1.space.s1', 'space.event.answers.changed', { space_cuid: 's1', event_cuid: 'e1', author_cuid: null }),
    );

    expect(list).toHaveBeenCalledTimes(1);
    expect(names(dialog)).toHaveLength(20);
  });

  it('drops the answer of a load that comes after the choice changed', async () => {
    const answers: Array<(result: ListResult) => void> = [];
    vi.spyOn(knowiiApiClient.communities.events.answers, 'list')
      .mockResolvedValueOnce(page(YES, 0, { yes: 45, replay: 3, no: 4 }))
      .mockImplementation(
        () =>
          new Promise((resolve) => {
            answers.push(resolve);
          }),
      );
    const dialog = await openDialog();
    await within(dialog).findByRole('list');

    // "Replay only", then "Yes" again before the first answer came
    await userEvent.click(within(dialog).getByRole('radio', { name: 'Replay only (3)' }));
    await userEvent.click(within(dialog).getByRole('radio', { name: 'Yes (45)' }));
    await act(async () => answers[1]?.(page(YES, 0, { yes: 45, replay: 3, no: 4 })));
    await act(async () => answers[0]?.(page(REPLAY, 0, { yes: 45, replay: 3, no: 4 })));

    expect(within(dialog).getByRole('list', { name: 'Members who answered Yes' })).toBeInTheDocument();
    expect(names(dialog)[0]).toBe('Member 01');
    expect(names(dialog)).toHaveLength(20);
  });

  it('has no automated violation with the names, the choice and "Load more" (axe)', async () => {
    serve(YES, REPLAY, 4);
    const dialog = await openDialog();
    await within(dialog).findByRole('list');

    await noViolation();
  });
});
