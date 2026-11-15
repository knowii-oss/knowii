import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { Community, CommunitySpaceLevel } from '@knowii/common';
import { spyOnFollowApi } from '@/test-utils/notifications';
import NotificationSettings from './Notifications';

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

const community = { cuid: 'c1', name: 'Tests', slug: 'tests' } as Community;

const row = (
  cuid: string,
  name: string,
  type: string,
  group: [string, string],
  level: CommunitySpaceLevel['level'] = 'mine',
): CommunitySpaceLevel => ({
  space_cuid: cuid,
  space_name: name,
  space_slug: cuid,
  space_type: type,
  space_group_cuid: group[0],
  space_group_name: group[1],
  level,
  chosen: false,
  default: 'mine',
});

const levels = [
  row('s1', 'Open discussion', 'discussion', ['g1', 'Talk']),
  row('s2', 'Lounge', 'chat', ['g1', 'Talk']),
  row('s3', 'Start here', 'pages', ['g1', 'Talk']),
  row('s4', 'Notes course', 'course', ['g2', 'Learn'], 'none'),
];

const renderPage = (communities: Community[] = [community]) => render(<NotificationSettings communities={communities} />);

async function choose(user: ReturnType<typeof userEvent.setup>, label: string, option: string, group = 'Talk') {
  const scope = within(await screen.findByRole('group', { name: group }));
  await user.click(await scope.findByRole('button', { name: new RegExp(`^${label}`) }));
  await user.click(await screen.findByRole('option', { name: option }));
}

describe('the notification settings page (#1497)', () => {
  let api: ReturnType<typeof spyOnFollowApi>;

  beforeEach(() => {
    api = spyOnFollowApi();
    api.forCommunity.mockResolvedValue({ ok: true, message: '', data: levels });
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('shows "Your spaces": one fieldset with a legend per space group and one labelled choice per space', async () => {
    renderPage();

    const talk = await screen.findByRole('group', { name: 'Talk' });
    expect(
      within(talk)
        .getAllByRole('button')
        .map((button) => button.id),
    ).toEqual(['notifications-space-s1', 'notifications-space-s2', 'notifications-space-s3', 'notifications-group-g1']);
    expect(within(talk).getByLabelText('Notifications for Open discussion')).toHaveTextContent('Only what is for me');
    expect(within(talk).getByLabelText('Notifications for Lounge')).toBeInTheDocument();
    expect(within(talk).getByLabelText('Same for all spaces of this group')).toBeInTheDocument();
    expect(within(screen.getByRole('group', { name: 'Learn' })).getByLabelText('Notifications for Notes course')).toHaveTextContent(
      'Muted',
    );
    expect(screen.getByRole('heading', { name: 'Your spaces' })).toBeInTheDocument();
    expect(api.forCommunity).toHaveBeenCalledWith('c1');
  });

  it('names each community when the member has more than one', async () => {
    renderPage([community, { cuid: 'c2', name: 'Second', slug: 'second' } as Community]);

    expect(await screen.findByRole('heading', { name: 'Your spaces in Tests' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Your spaces in Second' })).toBeInTheDocument();
    expect(api.forCommunity).toHaveBeenCalledWith('c2');
  });

  it('says so when the member belongs to no community', () => {
    renderPage([]);

    expect(screen.getByText('You do not belong to a community yet.')).toBeInTheDocument();
  });

  it('offers Everything for a discussion space and a chat space only', async () => {
    renderPage();
    const user = userEvent.setup();

    await user.click(await screen.findByLabelText('Notifications for Open discussion'));
    expect(
      within(await screen.findByRole('listbox'))
        .getAllByRole('option')
        .map((option) => option.textContent),
    ).toEqual(['Everything', 'Only what is for me', 'Muted']);
    await user.keyboard('{Escape}');

    await user.click(screen.getByLabelText('Notifications for Start here'));
    expect(
      within(await screen.findByRole('listbox'))
        .getAllByRole('option')
        .map((option) => option.textContent),
    ).toEqual(['Only what is for me', 'Muted']);
  });

  it('saves a change at once and says "Saved" in a status region', async () => {
    renderPage();
    const user = userEvent.setup();

    await choose(user, 'Notifications for Open discussion', 'Muted');

    expect(api.setLevel).toHaveBeenCalledWith('c1', 's1', 'none');
    await waitFor(() => expect(screen.getByLabelText('Notifications for Open discussion')).toHaveTextContent('Muted'));
    const status = screen.getAllByRole('status')[0];
    expect(status).toHaveTextContent('Saved');
    expect(status).toHaveAttribute('aria-live', 'polite');
  });

  it('clears "Saved" after a few seconds, so that a second "Saved" is announced again', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    renderPage();
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    await choose(user, 'Notifications for Open discussion', 'Muted');
    await waitFor(() => expect(screen.getAllByRole('status')[0]).toHaveTextContent('Saved'));

    await act(async () => {
      await vi.advanceTimersByTimeAsync(4100);
    });

    expect(screen.getAllByRole('status')[0]).toHaveTextContent('');
  });

  it('keeps the old value on an error, with an alert next to the control and the focus on the control', async () => {
    api.setLevel.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
    renderPage();
    const user = userEvent.setup();

    await choose(user, 'Notifications for Open discussion', 'Everything');

    const control = screen.getByLabelText('Notifications for Open discussion');
    expect(await screen.findByRole('alert')).toHaveTextContent('Not saved. Retry.');
    expect(control).toHaveTextContent('Only what is for me');
    await waitFor(() => expect(control).toHaveFocus());
    expect(screen.getAllByRole('status')[0]).toHaveTextContent('');
  });

  it('"Same for all spaces of this group" applies the level to the spaces that can take it and says how many', async () => {
    renderPage();
    const user = userEvent.setup();

    await choose(user, 'Same for all spaces of this group', 'Muted');

    await waitFor(() => expect(api.setLevel).toHaveBeenCalledTimes(3));
    expect(api.setLevel.mock.calls.map((call) => call.slice(1))).toEqual([
      ['s1', 'none'],
      ['s2', 'none'],
      ['s3', 'none'],
    ]);
    await waitFor(() => expect(screen.getAllByRole('status')[0]).toHaveTextContent('Applied to 3 spaces.'));
    expect(screen.getByLabelText('Notifications for Lounge')).toHaveTextContent('Muted');
    // The other group did not change
    expect(api.setLevel).not.toHaveBeenCalledWith('c1', 's4', expect.anything());
  });

  it('"Same for all spaces of this group" with Everything skips the spaces that have no such level', async () => {
    renderPage();
    const user = userEvent.setup();

    await choose(user, 'Same for all spaces of this group', 'Everything');

    await waitFor(() => expect(api.setLevel).toHaveBeenCalledTimes(2));
    expect(api.setLevel).not.toHaveBeenCalledWith('c1', 's3', 'all');
    await waitFor(() =>
      expect(screen.getAllByRole('status')[0]).toHaveTextContent(
        'Applied to 2 spaces. Everything exists only for discussion and chat spaces, so 1 space kept its choice.',
      ),
    );
  });

  it('says "Not saved. Retry." next to "Same for all" when one request fails, and keeps the spaces that failed', async () => {
    api.setLevel.mockImplementation((_community, space, level) =>
      Promise.resolve(space === 's2' ? { ok: false as const, status: 500, message: 'Failed', errors: {} } : api.level(level)),
    );
    renderPage();
    const user = userEvent.setup();

    await choose(user, 'Same for all spaces of this group', 'Muted');

    const group = await screen.findByRole('group', { name: 'Talk' });
    expect(await within(group).findByRole('alert')).toHaveTextContent('Not saved. Retry.');
    expect(within(group).getByLabelText('Notifications for Lounge')).toHaveTextContent('Only what is for me');
    expect(within(group).getByLabelText('Notifications for Open discussion')).toHaveTextContent('Muted');
  });

  it('puts the focus on the space of the link "Notification settings for <space>"', async () => {
    window.history.replaceState(null, '', '/user/notifications#notifications-space-s2');
    renderPage();

    await waitFor(() => expect(screen.getByLabelText('Notifications for Lounge')).toHaveFocus());
    window.history.replaceState(null, '', '/');
  });

  it('says so when the levels do not load', async () => {
    api.forCommunity.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('We could not load your spaces.');
  });

  it('has no automated violation (axe), with a saved change and an error', async () => {
    renderPage();
    const user = userEvent.setup();
    await choose(user, 'Notifications for Open discussion', 'Muted');
    await waitFor(() => expect(screen.getAllByRole('status')[0]).toHaveTextContent('Saved'));
    api.setLevel.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
    await choose(user, 'Notifications for Lounge', 'Muted');
    await screen.findByRole('alert');

    const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.html).join(' | ')}`)).toEqual([]);
  });
});
