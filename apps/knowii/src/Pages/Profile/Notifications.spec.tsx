import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { Community, CommunitySpaceLevel } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { spyOnFollowApi } from '@/test-utils/notifications';
import NotificationSettings from './Notifications';

const shared = vi.hoisted(() => ({
  user: { cuid: 'u1', time_zone: 'Europe/Brussels', notification_popups: true } as {
    cuid: string;
    time_zone: string | null | undefined;
    notification_popups?: boolean;
  },
  reload: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
  router: { reload: shared.reload },
  usePage: () => ({ props: { auth: { user: shared.user } }, url: '/user/notifications' }),
}));

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
    shared.user = { cuid: 'u1', time_zone: 'Europe/Brussels', notification_popups: true };
    shared.reload.mockClear();
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

describe('the time zone and the pop-ups of the settings page (#1476)', () => {
  beforeEach(() => {
    shared.user = { cuid: 'u1', time_zone: 'Europe/Brussels', notification_popups: true };
    shared.reload.mockClear();
    spyOnFollowApi().forCommunity.mockResolvedValue({ ok: true, message: '', data: levels });
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  const setTimeZone = () =>
    vi
      .spyOn(knowiiApiClient.users, 'setTimeZone')
      .mockImplementation((zone) => Promise.resolve({ ok: true, message: '', data: { time_zone: zone } }));

  it('shows "Your time zone": the zone of the account in words, and a choice of every zone with UTC', async () => {
    renderPage();
    const user = userEvent.setup();

    expect(screen.getByRole('heading', { name: 'Your time zone' })).toBeInTheDocument();
    expect(screen.getByText(/Times show in Central European (Standard )?Time \(Europe\/Brussels\)/)).toBeInTheDocument();
    const control = screen.getByLabelText('Time zone');
    expect(control).toHaveTextContent('Europe/Brussels');

    await user.click(control);
    const options = within(await screen.findByRole('listbox')).getAllByRole('option');
    expect(options.length).toBeGreaterThan(100);
    expect(options.map((option) => option.textContent)).toContain('UTC');
    expect(options.map((option) => option.textContent)).toContain('Asia/Tokyo');
  });

  it('says "Set your time zone" and names the zone that applies until then, when the account has none', () => {
    shared.user = { cuid: 'u1', time_zone: null };
    renderPage();

    expect(screen.getByText('Set your time zone.')).toBeInTheDocument();
    expect(screen.getByText(/Until you do, times show in/)).toBeInTheDocument();
    expect(screen.getByLabelText('Time zone')).toHaveTextContent('Choose a time zone');
  });

  it('saves a new zone at once, says "Saved", and loads the account again', async () => {
    const save = setTimeZone();
    renderPage();
    const user = userEvent.setup();

    await user.click(screen.getByLabelText('Time zone'));
    await user.click(await screen.findByRole('option', { name: 'Asia/Tokyo' }));

    expect(save).toHaveBeenCalledWith('Asia/Tokyo');
    await waitFor(() => expect(screen.getAllByRole('status')[0]).toHaveTextContent('Saved'));
    expect(screen.getByLabelText('Time zone')).toHaveTextContent('Asia/Tokyo');
    expect(shared.reload).toHaveBeenCalledWith({ only: ['auth'] });
  });

  it('keeps the old zone on an error, with "Not saved. Retry." and the focus on the control', async () => {
    vi.spyOn(knowiiApiClient.users, 'setTimeZone').mockResolvedValue({ ok: false, status: 422, message: 'Failed', errors: {} });
    renderPage();
    const user = userEvent.setup();

    await user.click(screen.getByLabelText('Time zone'));
    await user.click(await screen.findByRole('option', { name: 'Asia/Tokyo' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Not saved. Retry.');
    const control = screen.getByLabelText('Time zone');
    expect(control).toHaveTextContent('Europe/Brussels');
    await waitFor(() => expect(control).toHaveFocus());
    expect(shared.reload).not.toHaveBeenCalled();
  });

  it('shows the switch "Show pop-ups", on by default, and saves a change at once', async () => {
    const save = vi
      .spyOn(knowiiApiClient.users, 'setNotificationPopups')
      .mockResolvedValue({ ok: true, message: '', data: { notification_popups: false } });
    shared.user = { cuid: 'u1', time_zone: 'UTC' };
    renderPage();
    const user = userEvent.setup();

    const toggle = screen.getByRole('switch', { name: /Show pop-ups/ });
    expect(toggle).toBeChecked();
    await user.click(toggle);

    expect(save).toHaveBeenCalledWith(false);
    await waitFor(() => expect(screen.getAllByRole('status')[0]).toHaveTextContent('Saved'));
    expect(toggle).not.toBeChecked();
  });

  it('puts the switch back and says "Not saved. Retry." when the server refuses, and the switch keeps the focus', async () => {
    vi.spyOn(knowiiApiClient.users, 'setNotificationPopups').mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
    shared.user = { cuid: 'u1', time_zone: 'UTC', notification_popups: true };
    renderPage();
    const user = userEvent.setup();

    const toggle = screen.getByRole('switch', { name: /Show pop-ups/ });
    await user.click(toggle);

    expect(await screen.findByRole('alert')).toHaveTextContent('Not saved. Retry.');
    expect(toggle).toBeChecked();
    expect(toggle).not.toBeDisabled();
    expect(toggle).toHaveFocus();
  });

  it('shows the switch off when the account turned the pop-ups off', () => {
    shared.user = { cuid: 'u1', time_zone: 'UTC', notification_popups: false };
    renderPage();

    expect(screen.getByRole('switch', { name: /Show pop-ups/ })).not.toBeChecked();
  });

  it('has controls of 44 px and no automated violation (axe) with the new parts', async () => {
    const { container } = renderPage();
    await screen.findByRole('group', { name: 'Talk' });

    expect(screen.getByLabelText('Time zone').className).toContain('h-11');
    expect(screen.getByRole('switch', { name: /Show pop-ups/ })).toHaveClass('h-11', 'w-11');
    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
