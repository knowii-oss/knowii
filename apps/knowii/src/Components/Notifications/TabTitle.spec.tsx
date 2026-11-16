import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import { emitSocketEvent } from '@/test-utils/fake-echo';
import { spyOnNotificationApi } from '@/test-utils/notifications';
import { NOTIFICATION_DEBOUNCE_MS, NotificationsProvider } from './NotificationsProvider';
import { TabTitle } from './TabTitle';

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <div data-testid="tab-title">{title}</div>,
  Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
  usePage: () => ({ props: { communityCount: 1 }, url: '/' }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));

async function settle(milliseconds = 0) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(milliseconds);
  });
}

describe('the title of the browser tab (#1476, design 5.4)', () => {
  let api: ReturnType<typeof spyOnNotificationApi>;

  beforeEach(() => {
    vi.useFakeTimers();
    api = spyOnNotificationApi();
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  const renderTitle = () =>
    render(
      <NotificationsProvider userCuid="u1">
        <TabTitle title="Open discussion" />
      </NotificationsProvider>,
    );

  it.each([
    [0, 0, 'Open discussion'],
    [3, 0, '(3) Open discussion'],
    [0, 2, '(2) Open discussion'],
    [2, 1, '(3) Open discussion'],
    [9, 0, '(9) Open discussion'],
    [7, 3, '(9+) Open discussion'],
    [40, 0, '(9+) Open discussion'],
  ])('with a bell number of %i and %i messages the title is "%s"', async (count, messages, title) => {
    api.count.mockResolvedValue({ ok: true, message: '', data: { count, messages } });
    renderTitle();
    await settle();

    expect(screen.getByTestId('tab-title').textContent).toBe(title);
  });

  it('follows the count of the server, and never counts by itself', async () => {
    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 1, messages: 0 } });
    renderTitle();
    await settle();
    expect(screen.getByTestId('tab-title')).toHaveTextContent('(1) Open discussion');

    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 5, messages: 2 } });
    emitSocketEvent('user.u1', 'notifications.changed', { notification_cuid: 'n1', community_cuid: 'c1' });
    await settle(NOTIFICATION_DEBOUNCE_MS);

    expect(screen.getByTestId('tab-title')).toHaveTextContent('(7) Open discussion');

    api.count.mockResolvedValue({ ok: true, message: '', data: { count: 0, messages: 0 } });
    emitSocketEvent('user.u1', 'notifications.changed', { notification_cuid: null, community_cuid: 'c1' });
    await settle(NOTIFICATION_DEBOUNCE_MS);

    expect(screen.getByTestId('tab-title')).toHaveTextContent(/^Open discussion$/);
  });

  it('shows the title alone for a visitor: no provider, so no number', () => {
    render(<TabTitle title="Open discussion" />);

    expect(screen.getByTestId('tab-title')).toHaveTextContent(/^Open discussion$/);
    expect(api.count).not.toHaveBeenCalled();
  });
});
