import { afterEach, describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';
import { joinedPresenceChannels } from '@/test-utils/fake-echo';
import { spyOnNotificationApi } from '@/test-utils/notifications';
import { NotificationsProvider } from './NotificationsProvider';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { communityCount: 1 }, url: '/' }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));

describe('the live connection of a signed-in page (ADR 0075)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('joins the presence channel of the account at once, so that the server can end the connections of the account', () => {
    spyOnNotificationApi();
    const join = vi.spyOn(window.Echo, 'join');

    const { unmount } = render(<NotificationsProvider userCuid="u1">{null}</NotificationsProvider>);

    expect(joinedPresenceChannels()).toEqual(['user.u1']);
    expect(join).toHaveBeenCalledWith('user.u1');

    // The next page of the same account keeps the channel: it is never left while the page lives
    const leave = vi.spyOn(window.Echo, 'leaveChannel');
    unmount();
    expect(leave).not.toHaveBeenCalledWith('presence-user.u1');
  });
});
