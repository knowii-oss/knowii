import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import axe from 'axe-core';
import { emitSocketEvent } from '@/test-utils/fake-echo';
import { makeNotification, page, person, spyOnNotificationApi } from '@/test-utils/notifications';
import { NotificationBell } from './NotificationBell';
import { NotificationPopup, popupDurationMs, POPUP_MAX_MS } from './NotificationPopup';
import { NOTIFICATION_DEBOUNCE_MS, NOTIFICATION_POPUP_WINDOW_MS, NotificationsProvider } from './NotificationsProvider';

const shared = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: shared.props, url: '/' }),
}));
vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, params: Record<string, string>) => `/${name}/${Object.values(params).join('/')}`,
}));

const channel = 'user.u1';

async function settle(milliseconds = 0) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(milliseconds);
  });
}

function setVisibility(state: 'visible' | 'hidden') {
  Object.defineProperty(document, 'visibilityState', { value: state, configurable: true });
  document.dispatchEvent(new Event('visibilitychange'));
}

describe('the duration of the pop-up (#1476, design 5.3.3)', () => {
  it('is 8 seconds plus 1 second per 20 characters, 20 seconds at most', () => {
    expect(popupDurationMs('')).toBe(8000);
    expect(popupDurationMs('x'.repeat(20))).toBe(9000);
    expect(popupDurationMs('x'.repeat(40))).toBe(10_000);
    expect(popupDurationMs('x'.repeat(100))).toBe(13_000);
    expect(popupDurationMs('x'.repeat(5000))).toBe(POPUP_MAX_MS);
  });
});

describe('the pop-up (#1476, design 5.3 and 9.4)', () => {
  const text = 'Ben replied to your post';
  const renderPopup = (props: Partial<React.ComponentProps<typeof NotificationPopup>> = {}) => {
    const onClose = vi.fn();
    const onOpen = vi.fn();
    const view = render(<NotificationPopup text={text} href="/posts/a" onOpen={onOpen} onClose={onClose} {...props} />);
    return { ...view, onClose, onOpen };
  };

  beforeEach(() => {
    vi.useFakeTimers();
    setVisibility('visible');
  });
  afterEach(() => {
    vi.useRealTimers();
    setVisibility('visible');
  });

  it('holds the sentence as one link and a close button named "Close the notification", and nothing else', () => {
    renderPopup();

    const popup = screen.getByTestId('notification-popup');
    expect(screen.getByRole('link', { name: text })).toHaveAttribute('href', '/posts/a');
    expect(screen.getByRole('button', { name: 'Close the notification' })).toBeInTheDocument();
    expect(popup.querySelectorAll('a, button')).toHaveLength(2);
    // The text is not a live region: the one polite region of the layout says it
    expect(popup.querySelector('[aria-live], [role="status"], [role="alert"]')).toBeNull();
  });

  it('never takes the focus', () => {
    renderPopup();

    expect(document.body).toHaveFocus();
  });

  it('closes itself after the time of its text', async () => {
    const { onClose } = renderPopup();

    await settle(popupDurationMs(text) - 1);
    expect(onClose).not.toHaveBeenCalled();
    await settle(1);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('stops the timer while the pointer is on it, and goes on with the time that was left', async () => {
    const { onClose } = renderPopup();
    const popup = screen.getByTestId('notification-popup');

    await settle(5000);
    fireEvent.pointerEnter(popup);
    await settle(60_000);
    expect(onClose).not.toHaveBeenCalled();

    fireEvent.pointerLeave(popup);
    await settle(popupDurationMs(text) - 5000 - 1);
    expect(onClose).not.toHaveBeenCalled();
    await settle(1);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('stops the timer while the focus is in it', async () => {
    const { onClose } = renderPopup();

    screen.getByRole('button', { name: 'Close the notification' }).focus();
    await settle(60_000);
    expect(onClose).not.toHaveBeenCalled();

    screen.getByRole('button', { name: 'Close the notification' }).blur();
    await settle(popupDurationMs(text));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('stops the timer while the tab is hidden', async () => {
    const { onClose } = renderPopup();

    await settle(4000);
    act(() => setVisibility('hidden'));
    await settle(60_000);
    expect(onClose).not.toHaveBeenCalled();

    act(() => setVisibility('visible'));
    await settle(popupDurationMs(text) - 4000);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('closes with Escape only when the focus is in it', async () => {
    const { onClose } = renderPopup();

    fireEvent.keyDown(document.body, { key: 'Escape' });
    expect(onClose).not.toHaveBeenCalled();

    screen.getByRole('link', { name: text }).focus();
    fireEvent.keyDown(screen.getByRole('link', { name: text }), { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('closes with the button, and tells when the link was followed', () => {
    const { onClose, onOpen } = renderPopup();

    fireEvent.click(screen.getByRole('link', { name: text }));
    expect(onOpen).toHaveBeenCalledTimes(1);
    fireEvent.click(screen.getByRole('button', { name: 'Close the notification' }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('sits at the top and does not slide with prefers-reduced-motion', () => {
    renderPopup();

    const popup = screen.getByTestId('notification-popup');
    // At the top, so that it covers neither a composer nor the bottom navigation at 360 px
    expect(popup.className).toContain('top-20');
    expect(popup.className).not.toContain('bottom-');
    // The slide only exists under motion-safe
    expect(popup.className).toContain('motion-safe:animate-toast-in');
    expect(popup.className.split(' ').filter((name) => name.startsWith('animate-'))).toEqual([]);
  });

  it('has no automated violation (axe)', async () => {
    vi.useRealTimers();
    const { container } = renderPopup();

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});

describe('when a pop-up shows (#1476, design 5.3.1 and 5.3.2)', () => {
  let api: ReturnType<typeof spyOnNotificationApi>;

  beforeEach(() => {
    vi.useFakeTimers();
    setVisibility('visible');
    shared.props = { communityCount: 1, auth: { user: { cuid: 'u1', notification_popups: true } } };
    window.history.pushState({}, '', '/');
    api = spyOnNotificationApi();
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    setVisibility('visible');
  });

  const renderApp = () =>
    render(
      <NotificationsProvider userCuid="u1">
        <NotificationBell />
      </NotificationsProvider>,
    );

  const arrival = async (cuid: string, name: string, changes = {}) => {
    api.list.mockResolvedValue(page([makeNotification(cuid, { actor: person(name), actors: [person(name)], ...changes })]));
    emitSocketEvent(channel, 'notifications.changed', { notification_cuid: cuid, community_cuid: 'c1' });
    await settle(NOTIFICATION_DEBOUNCE_MS);
  };

  const popup = () => screen.queryByTestId('notification-popup');

  it('shows a pop-up for a new level 1 line, with the sentence as the link to the item', async () => {
    renderApp();
    await settle();

    await arrival('n1', 'Ben');

    expect(popup()).toBeInTheDocument();
    const link = screen.getByRole('link', { name: 'Ben replied to your post' });
    expect(link).toHaveAttribute('href', '/posts.show/knowii/open/post-n1#comment-comment-n1');
    // The same sentence goes through the one polite live region, once
    expect(screen.getAllByRole('status')).toHaveLength(1);
    expect(screen.getByRole('status')).toHaveTextContent('Ben replied to your post');
  });

  it('shows nothing for a line of Following (level 2)', async () => {
    renderApp();
    await settle();

    await arrival('n1', 'Ben', { level: 2, kind: 'thread_comment' });

    expect(popup()).toBeNull();
  });

  it('shows nothing when the member turned the pop-ups off', async () => {
    shared.props = { communityCount: 1, auth: { user: { cuid: 'u1', notification_popups: false } } };
    renderApp();
    await settle();

    await arrival('n1', 'Ben');

    expect(popup()).toBeNull();
    // The live region still says it
    expect(screen.getByRole('status')).toHaveTextContent('Ben replied to your post');
  });

  it('shows nothing while the tab is hidden', async () => {
    renderApp();
    await settle();
    setVisibility('hidden');

    await arrival('n1', 'Ben');

    expect(popup()).toBeNull();
  });

  it('shows nothing for the thread that is open on the page', async () => {
    window.history.pushState({}, '', '/posts.show/knowii/open/post-n1');
    renderApp();
    await settle();

    await arrival('n1', 'Ben');

    expect(popup()).toBeNull();
  });

  it('shows one for another thread than the open one', async () => {
    window.history.pushState({}, '', '/posts.show/knowii/open/post-other');
    renderApp();
    await settle();

    await arrival('n1', 'Ben');

    expect(popup()).toBeInTheDocument();
  });

  it('shows nothing while the panel is open, and closes the pop-up that is there when the panel opens', async () => {
    renderApp();
    await settle();
    await arrival('n1', 'Ben');
    expect(popup()).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /^Notifications/ }));
    await settle();
    expect(popup()).toBeNull();

    await arrival('n2', 'Carl');
    expect(popup()).toBeNull();
  });

  it('shows one at a time: a second line within 30 seconds replaces the first with "<N> new for you"', async () => {
    renderApp();
    await settle();

    await arrival('n1', 'Ben');
    await settle(5000);
    await arrival('n2', 'Carl');

    expect(screen.getAllByTestId('notification-popup')).toHaveLength(1);
    expect(screen.getByRole('link', { name: '2 new for you' })).toHaveAttribute('href', '/notifications.index/');

    await settle(5000);
    await arrival('n3', 'Dana');
    expect(screen.getByRole('link', { name: '3 new for you' })).toBeInTheDocument();
    expect(screen.getAllByTestId('notification-popup')).toHaveLength(1);
  });

  it('counts a line that comes within 30 seconds after the first pop-up closed', async () => {
    renderApp();
    await settle();

    await arrival('n1', 'Ben');
    await settle(POPUP_MAX_MS);
    expect(popup()).toBeNull();
    await arrival('n2', 'Carl');

    expect(screen.getByRole('link', { name: '2 new for you' })).toHaveAttribute('href', '/notifications.index/');
  });

  it('starts again with the sentence of the line when the 30 seconds are over', async () => {
    renderApp();
    await settle();

    await arrival('n1', 'Ben');
    await settle(NOTIFICATION_POPUP_WINDOW_MS + 1000);
    // The first pop-up is gone after 20 seconds at most
    expect(popup()).toBeNull();
    await arrival('n2', 'Carl');

    expect(screen.getByRole('link', { name: 'Carl replied to your post' })).toBeInTheDocument();
  });

  it('closes after its time, and the close button works', async () => {
    renderApp();
    await settle();
    await arrival('n1', 'Ben');

    fireEvent.click(screen.getByRole('button', { name: 'Close the notification' }));
    expect(popup()).toBeNull();

    await arrival('n2', 'Carl');
    expect(popup()).toBeInTheDocument();
    await settle(POPUP_MAX_MS);
    expect(popup()).toBeNull();
  });

  it('marks the line as read when the member follows its link', async () => {
    renderApp();
    await settle();
    await arrival('n1', 'Ben');

    fireEvent.click(screen.getByRole('link', { name: 'Ben replied to your post' }));

    expect(api.read).toHaveBeenCalledWith('n1');
    expect(popup()).toBeNull();
  });
});
