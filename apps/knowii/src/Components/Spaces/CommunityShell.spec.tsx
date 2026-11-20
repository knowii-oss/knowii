import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { fakeEcho } from '@/test-utils/fake-echo';
import { CommunityShell } from './CommunityShell';

// The user of the page: null for a visitor. And the address of the page that is open
const signedIn = vi.hoisted(() => ({ user: null as { cuid: string; show_online_status: boolean } | null, url: '/' }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { reload: vi.fn() },
  usePage: () => ({ props: { auth: { user: signedIn.user } }, url: signedIn.url }),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: () => '/space', writable: true });
  Object.defineProperty(window, 'Echo', {
    value: { private: () => ({ listen: () => undefined, stopListening: () => undefined }), leaveChannel: () => undefined },
    writable: true,
  });
});

const community = { cuid: 'c1', name: 'Tests', slug: 'tests' } as Community;

function renderShell(contentWidth?: 'wide' | 'readable') {
  render(
    <AppDataProvider>
      <CommunityShell community={community} spaceGroups={[]} {...(contentWidth === undefined ? {} : { contentWidth })}>
        <p>Content</p>
      </CommunityShell>
    </AppDataProvider>,
  );
  return screen.getByText('Content');
}

describe('CommunityShell (#1264)', () => {
  it('makes the content a container, so that its layouts follow the width left by the navigation', () => {
    const content = renderShell();

    expect(content.closest('.\\@container')).not.toBeNull();
    expect(content.closest('.readable-width')).toBeNull();
  });

  it('keeps long text at a readable width when asked', () => {
    const content = renderShell('readable');

    expect(content.parentElement).toHaveClass('readable-width');
    expect(content.closest('.\\@container')).not.toBeNull();
  });

  it('names the navigation of the spaces', () => {
    renderShell();

    expect(screen.getByRole('complementary', { name: 'Tests spaces' })).toBeInTheDocument();
  });
});

describe('CommunityShell for a visitor (#1387)', () => {
  const channelOpened = vi.fn();

  beforeEach(() => {
    channelOpened.mockReset();
    Object.defineProperty(window, 'Echo', {
      value: {
        private: (channel: string) => {
          channelOpened(channel);
          return { listen: () => undefined, stopListening: () => undefined };
        },
        join: (channel: string) => {
          channelOpened(channel);
          return { here: () => undefined, joining: () => undefined, leaving: () => undefined };
        },
        leaveChannel: () => undefined,
      },
      writable: true,
    });
  });

  afterEach(() => {
    signedIn.user = null;
  });

  it('opens no channel and no presence without a user', () => {
    signedIn.user = null;
    renderShell();

    expect(channelOpened).not.toHaveBeenCalled();
    expect(screen.queryByText('Messages')).toBeNull();
  });

  it('opens its channels for a signed-in member', () => {
    signedIn.user = { cuid: 'u1', show_online_status: true };
    renderShell();

    expect(channelOpened).toHaveBeenCalledWith('community.c1');
    expect(channelOpened).toHaveBeenCalledWith('user.u1');
  });
});

describe('the links of a member above the spaces: "Messages" and "Members" (#1186, #148)', () => {
  // The addresses of the pages, as the routes of the application build them
  const routes: Record<string, string> = {
    'messages.index': '/community/tests/messages',
    'members.index': '/community/tests/members',
    'members.show': '/community/tests/member/ada',
    'spaces.show': '/community/tests/space/general',
  };
  let previousRoute: unknown;
  let previousEcho: unknown;

  beforeAll(() => {
    previousRoute = (globalThis as { route?: unknown }).route;
    previousEcho = (window as { Echo?: unknown }).Echo;
    Object.defineProperty(globalThis, 'route', { value: (name: string) => routes[name] ?? '/', writable: true });
    // A member joins the presence channel of the community (#1187)
    Object.defineProperty(window, 'Echo', { value: fakeEcho, writable: true });
    vi.spyOn(knowiiApiClient.communities, 'onlineMembers').mockResolvedValue({ ok: true, message: '', data: [] });
  });
  afterAll(() => {
    Object.defineProperty(globalThis, 'route', { value: previousRoute, writable: true });
    Object.defineProperty(window, 'Echo', { value: previousEcho, writable: true });
    vi.restoreAllMocks();
  });
  beforeEach(() => {
    signedIn.user = { cuid: 'u1', show_online_status: true };
    signedIn.url = '/community/tests';
  });
  afterEach(() => {
    signedIn.user = null;
    signedIn.url = '/';
  });

  function renderFor(changes: Partial<Community> = {}) {
    return render(
      <AppDataProvider>
        <CommunityShell community={{ ...community, is_member: true, visibility: 'private', ...changes } as Community} spaceGroups={[]}>
          <p>Content</p>
        </CommunityShell>
      </AppDataProvider>,
    );
  }

  const sidebar = () => screen.getByRole('complementary', { name: 'Tests spaces' });
  const openDrawer = async () => {
    await userEvent.click(screen.getByRole('button', { name: 'Spaces' }));
    return screen.findByRole('dialog', { name: 'Tests' });
  };

  it('shows "Members" to a member in the sidebar, under "Messages" and above the spaces', () => {
    renderFor();

    const links = within(sidebar()).getAllByRole('link');
    expect(links.map((link) => link.textContent)).toEqual(['Messages', 'Members']);
    expect(links[1]).toHaveAttribute('href', '/community/tests/members');
    // Above the navigation of the spaces
    const spaces = within(sidebar()).getByRole('navigation');
    expect((links[1] as HTMLElement).compareDocumentPosition(spaces) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    // The icon is a decoration: the word is the name of the link
    expect(within(sidebar()).getByRole('link', { name: 'Members' }).querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
  });

  it('shows "Members" in the drawer of a narrow screen, under "Messages", and closes the drawer on a press', async () => {
    renderFor();

    const drawer = await openDrawer();
    const links = within(drawer)
      .getAllByRole('link')
      .map((link) => link.textContent);
    expect(links.slice(0, 2)).toEqual(['Messages', 'Members']);

    const members = within(drawer).getByRole('link', { name: 'Members' });
    expect(members).toHaveAttribute('href', '/community/tests/members');
    members.addEventListener('click', (event) => event.preventDefault());
    await userEvent.click(members);

    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Tests' })).toBeNull());
  });

  it('gives the two links one look', () => {
    renderFor();

    const messages = within(sidebar()).getByRole('link', { name: 'Messages' });
    const members = within(sidebar()).getByRole('link', { name: 'Members' });
    expect(members.className).toBe(messages.className);
  });

  it('does not show "Members" to a visitor', () => {
    signedIn.user = null;
    renderFor({ is_member: false });

    expect(screen.queryByRole('link', { name: 'Members' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Messages' })).toBeNull();
  });

  it('does not show "Members" to a signed-in person who is not a member', () => {
    renderFor({ is_member: false });

    expect(screen.queryByRole('link', { name: 'Members' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Messages' })).toBeNull();
  });

  it('does not show "Members" in a personal community, which has one member; "Messages" stays', async () => {
    renderFor({ visibility: 'personal' });

    expect(within(sidebar()).queryByRole('link', { name: 'Members' })).toBeNull();
    expect(within(sidebar()).getByRole('link', { name: 'Messages' })).toBeInTheDocument();

    const drawer = await openDrawer();
    expect(within(drawer).queryByRole('link', { name: 'Members' })).toBeNull();
    expect(within(drawer).getByRole('link', { name: 'Messages' })).toBeInTheDocument();
  });

  it('marks "Members" as the open page on the directory, with a marker that is not only a color', () => {
    signedIn.url = '/community/tests/members?sort=newest&query=ada';
    renderFor();

    const members = within(sidebar()).getByRole('link', { name: 'Members' });
    expect(members).toHaveAttribute('aria-current', 'page');
    expect(members).toHaveClass('font-bold');
    expect(within(sidebar()).getByRole('link', { name: 'Messages' })).not.toHaveAttribute('aria-current');
  });

  it.each(['/community/tests', '/community/tests/member/ada', '/community/tests/messages', '/community/tests/members-of-honor'])(
    'does not mark "Members" on %s',
    (url) => {
      signedIn.url = url;
      renderFor();

      const members = within(sidebar()).getByRole('link', { name: 'Members' });
      expect(members).not.toHaveAttribute('aria-current');
      expect(members).not.toHaveClass('font-bold');
    },
  );

  it('keeps the count of the unread messages on "Messages", and marks it on the pages of the messages', () => {
    signedIn.url = '/community/tests/messages/abc';
    renderFor({ unread_direct_messages: 3 });

    const messages = within(sidebar()).getByRole('link', { name: /Messages/ });
    expect(messages).toHaveTextContent('3');
    expect(messages).toHaveAccessibleName(/3 unread/);
    expect(messages).toHaveAttribute('aria-current', 'page');
    // "Members" has no count
    expect(within(sidebar()).getByRole('link', { name: 'Members' })).toHaveTextContent(/^Members$/);
  });
});
