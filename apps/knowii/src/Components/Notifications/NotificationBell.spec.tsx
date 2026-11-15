import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import { emitConnectionState, emitSocketEvent } from '@/test-utils/fake-echo';
import { failure, makeNotification, page, person, spyOnNotificationApi } from '@/test-utils/notifications';
import { NotificationBell } from './NotificationBell';
import { NOTIFICATION_ANNOUNCE_WINDOW_MS, NOTIFICATION_DEBOUNCE_MS, NotificationsProvider } from './NotificationsProvider';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: { communityCount: 1 }, url: '/' }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));

const channel = 'user.u1';

function renderBell() {
  return render(
    <NotificationsProvider userCuid="u1">
      <NotificationBell />
    </NotificationsProvider>,
  );
}

async function settle(milliseconds = 0) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(milliseconds);
  });
}

describe('NotificationBell, the name and the badge (#1484)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it.each([
    [0, 'Notifications', null],
    [3, 'Notifications, 3 new', '3'],
    [12, 'Notifications, 9 or more new', '9+'],
  ])('with a count of %i the name is "%s" and the badge %s', async (count, name, badge) => {
    const api = spyOnNotificationApi();
    api.count.mockResolvedValue({ ok: true, message: '', data: { count } });
    renderBell();

    const bell = await screen.findByRole('button', { name });
    if (badge === null) {
      expect(bell.querySelector('[aria-hidden="true"]:not(svg)')).toBeNull();
    } else {
      // The badge is hidden from assistive technology: the name holds the number
      const text = screen.getByText(badge);
      expect(text).toHaveAttribute('aria-hidden', 'true');
      expect(bell).toContainElement(text);
    }
  });

  it('is a button with aria-expanded and aria-controls', async () => {
    spyOnNotificationApi();
    renderBell();

    const bell = screen.getByRole('button', { name: 'Notifications' });
    expect(bell).toHaveAttribute('aria-expanded', 'false');
    expect(bell).toHaveAttribute('aria-controls', 'notifications-panel');
  });
});

describe('the number is the value of the server (#1484, design 5.2.6)', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('loads the count on page load, on each event, and keeps the server value: it never adds 1', async () => {
    const api = spyOnNotificationApi();
    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 3 } });
    renderBell();
    await settle();
    expect(screen.getByRole('button', { name: 'Notifications, 3 new' })).toBeInTheDocument();
    expect(api.count).toHaveBeenCalledTimes(1);

    // An event for a new row: the server still says 3 (for example the row bundled into a line that was counted)
    emitSocketEvent(channel, 'notifications.changed', { notification_cuid: 'n1', community_cuid: 'c1' });
    await settle(NOTIFICATION_DEBOUNCE_MS);
    expect(api.count).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('button', { name: 'Notifications, 3 new' })).toBeInTheDocument();

    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 7 } });
    emitSocketEvent(channel, 'notifications.changed', { notification_cuid: null, community_cuid: 'c1' });
    await settle(NOTIFICATION_DEBOUNCE_MS);
    expect(screen.getByRole('button', { name: 'Notifications, 7 new' })).toBeInTheDocument();
  });

  it('gives one request for several events in a short time', async () => {
    const api = spyOnNotificationApi();
    renderBell();
    await settle();
    expect(api.count).toHaveBeenCalledTimes(1);

    for (const cuid of ['n1', 'n2', 'n3', null]) {
      emitSocketEvent(channel, 'notifications.changed', { notification_cuid: cuid, community_cuid: 'c1' });
      await settle(50);
    }
    await settle(NOTIFICATION_DEBOUNCE_MS);

    expect(api.count).toHaveBeenCalledTimes(2);
  });

  it('loads the count again when the window gets the focus', async () => {
    const api = spyOnNotificationApi();
    renderBell();
    await settle();

    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 2 } });
    act(() => {
      window.dispatchEvent(new Event('focus'));
    });
    await settle();

    expect(api.count).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('button', { name: 'Notifications, 2 new' })).toBeInTheDocument();
  });

  it('loads the count again when the socket reconnects, and not for the first connection', async () => {
    const api = spyOnNotificationApi();
    renderBell();
    await settle();

    // The connection drops and comes back
    act(() => emitConnectionState('connected', 'unavailable'));
    expect(api.count).toHaveBeenCalledTimes(1);
    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 4 } });
    act(() => emitConnectionState('unavailable', 'connected'));
    await settle();

    expect(api.count).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('button', { name: 'Notifications, 4 new' })).toBeInTheDocument();
  });

  it('keeps the old number when the request fails', async () => {
    const api = spyOnNotificationApi();
    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 5 } });
    renderBell();
    await settle();

    api.count.mockResolvedValue(failure());
    act(() => {
      window.dispatchEvent(new Event('focus'));
    });
    await settle();

    expect(screen.getByRole('button', { name: 'Notifications, 5 new' })).toBeInTheDocument();
  });
});

describe('the live region (#1484, design 9.3)', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  const arrival = async (api: ReturnType<typeof spyOnNotificationApi>, cuid: string, name: string) => {
    api.list.mockResolvedValue(page([makeNotification(cuid, { actor: person(name), actors: [person(name)] })]));
    emitSocketEvent(channel, 'notifications.changed', { notification_cuid: cuid, community_cuid: 'c1' });
    await settle(NOTIFICATION_DEBOUNCE_MS);
  };

  it('exists from the load, is polite, and is empty', () => {
    spyOnNotificationApi();
    renderBell();

    const region = screen.getByRole('status');
    expect(region).toHaveAttribute('aria-live', 'polite');
    expect(region).toBeEmptyDOMElement();
  });

  it('says the actor and the verb of a new row that the member did not see', async () => {
    const api = spyOnNotificationApi();
    renderBell();
    await settle();

    await arrival(api, 'n1', 'Ben');

    expect(screen.getByRole('status')).toHaveTextContent('Ben replied to your post');
  });

  it('says nothing for a row that was seen, or for a change of the count', async () => {
    const api = spyOnNotificationApi();
    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 1 } });
    renderBell();
    await settle();

    api.list.mockResolvedValue(page([makeNotification('n1', { seen_at: new Date() })]));
    emitSocketEvent(channel, 'notifications.changed', { notification_cuid: 'n1', community_cuid: 'c1' });
    await settle(NOTIFICATION_DEBOUNCE_MS);
    // Several rows changed: no cuid, so nothing to name
    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 9 } });
    emitSocketEvent(channel, 'notifications.changed', { notification_cuid: null, community_cuid: 'c1' });
    await settle(NOTIFICATION_DEBOUNCE_MS);

    expect(screen.getByRole('button', { name: 'Notifications, 9 new' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toBeEmptyDOMElement();
  });

  it('speaks once per 30 seconds: the arrivals of the window become "N new for you"', async () => {
    const api = spyOnNotificationApi();
    renderBell();
    await settle();

    await arrival(api, 'n1', 'Ben');
    expect(screen.getByRole('status')).toHaveTextContent('Ben replied to your post');

    await settle(5000);
    await arrival(api, 'n2', 'Carl');
    await settle(5000);
    await arrival(api, 'n3', 'Dana');
    // Still the first message: the window of 30 seconds is not over
    expect(screen.getByRole('status')).toHaveTextContent('Ben replied to your post');

    await settle(NOTIFICATION_ANNOUNCE_WINDOW_MS);
    expect(screen.getByRole('status')).toHaveTextContent('2 new for you');
  });

  it('does not announce the same row twice', async () => {
    const api = spyOnNotificationApi();
    renderBell();
    await settle();

    await arrival(api, 'n1', 'Ben');
    await settle(NOTIFICATION_ANNOUNCE_WINDOW_MS + 1000);
    await arrival(api, 'n1', 'Ben');
    await settle(NOTIFICATION_ANNOUNCE_WINDOW_MS + 1000);

    expect(screen.getByRole('status')).toHaveTextContent('Ben replied to your post');
    expect(document.querySelectorAll('[aria-live="assertive"]')).toHaveLength(0);
  });
});
