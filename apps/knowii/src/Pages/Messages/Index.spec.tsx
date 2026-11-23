import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import type { Author, Community } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import MessagesPage from './Index';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<Record<string, unknown>>()),
  useTypedPage: () => ({ props: { auth: { user: { cuid: 'me', name: 'Me', username: 'me', show_online_status: true } } } }),
  useSocket: vi.fn(),
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}));

vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string, params?: Record<string, string>) => `/${name}/${Object.values(params ?? {}).join('/')}`,
    writable: true,
  });
  Element.prototype.scrollIntoView = vi.fn();
});

afterEach(() => {
  vi.restoreAllMocks();
});

const community = { cuid: 'c1', name: 'Tests', slug: 'tests', visibility: 'private' } as Community;
const quinn: Author = { cuid: 'q1', name: 'Quinn Hidden', username: 'quinn', avatar_url: null };

function renderPage(recipient: Author | null, canStart = true) {
  return render(
    <AppDataProvider>
      <MessagesPage
        community={community}
        spaceGroups={[]}
        conversations={[]}
        conversation={null}
        messages={null}
        permissions={{ canStart, canSend: false }}
        blockedByMe={false}
        recipient={recipient}
      />
    </AppDataProvider>,
  );
}

describe('the Messages page, "Send a message" from a member page (#1466)', () => {
  it('opens the dialog with the recipient of the server, and does not search the member list', async () => {
    const search = vi.spyOn(knowiiApiClient.communities.members, 'search');
    renderPage(quinn);

    const dialog = await screen.findByRole('dialog', { name: 'New message' });
    expect(within(dialog).getByText('Quinn Hidden')).toBeInTheDocument();
    expect(search).not.toHaveBeenCalled();
  });

  it('opens nothing without a recipient', async () => {
    const search = vi.spyOn(knowiiApiClient.communities.members, 'search');
    renderPage(null);

    await Promise.resolve();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(search).not.toHaveBeenCalled();
  });

  it('opens nothing when the community has no direct messages', async () => {
    renderPage(quinn, false);

    await Promise.resolve();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
