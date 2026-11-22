import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { InviteLinkCommunity, InviteLinkPageState } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import { Toaster } from '@/Components/ui';
import { INVITE_LINK_WORDS } from '@/Components/InviteLinks/invite-link-words';
import InviteLinkPage from './Show';

const page = vi.hoisted(() => ({ user: null as { cuid: string } | null, title: '', errors: {} as Record<string, string> }));
const router = vi.hoisted(() => ({ visit: vi.fn(), reload: vi.fn(), post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router,
}));

vi.mock('@knowii/common', async () => ({
  ...(await vi.importActual<Record<string, unknown>>('@knowii/common')),
  useTypedPage: () => ({ props: { auth: { user: page.user }, errors: page.errors } }),
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children, browserPageTitle }: { children: React.ReactNode; browserPageTitle: string }) => {
    page.title = browserPageTitle;
    return <main>{children}</main>;
  },
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string, params?: Record<string, string>) => `/${name}${params ? `/${Object.values(params).join('/')}` : ''}`,
    writable: true,
  });
});

const community: InviteLinkCommunity = {
  cuid: 'c1',
  name: 'Harbour of Links',
  slug: 'harbour-of-links',
  description: 'A harbour of example',
  visibility: 'private',
  join_policy: 'invitation',
  is_member: false,
};

function renderPage(state: InviteLinkPageState, signedIn = true, errors: Record<string, string> = {}) {
  page.user = signedIn ? { cuid: 'u1' } : null;
  page.errors = errors;
  return render(
    <AppDataProvider>
      <InviteLinkPage community={state === 'unusable' ? null : community} state={state} />
      <Toaster />
    </AppDataProvider>,
  );
}

beforeEach(() => {
  vi.restoreAllMocks();
  router.visit.mockReset();
  router.post.mockReset();
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('The page of an invite link (#1391)', () => {
  it('a visitor: the title, the description, "Sign up to join", "Sign in" and the hint, no "Join"', () => {
    renderPage('join', false);

    expect(screen.getByRole('heading', { level: 1, name: 'Join Harbour of Links' })).toBeInTheDocument();
    expect(page.title).toBe('Join Harbour of Links');
    expect(screen.getByText('A harbour of example')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sign up to join' })).toHaveAttribute('href', '/register');
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login');
    expect(screen.getByText(INVITE_LINK_WORDS.visitorHint)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Join' })).toBeNull();
  });

  it('a signed-in person joins with a POST of the page: the button is busy once, then the community opens with the toast', async () => {
    renderPage('join');

    const button = screen.getByRole('button', { name: 'Join' });
    await userEvent.click(button);
    expect(button).toHaveAttribute('aria-busy', 'true');
    // A second click while it works does nothing
    await userEvent.click(button);
    expect(router.post).toHaveBeenCalledTimes(1);
    // The page sends the cuid of its community, never a token
    expect(router.post).toHaveBeenCalledWith('/invite-links.join', { community: 'c1' }, expect.objectContaining({ preserveScroll: true }));

    const options = router.post.mock.calls[0]?.[2] as { onSuccess: () => void; onFinish: () => void };
    act(() => {
      options.onSuccess();
      options.onFinish();
    });
    expect(await screen.findByText('Welcome to Harbour of Links')).toBeInTheDocument();
    expect(button).not.toHaveAttribute('aria-busy', 'true');
  });

  it('a refused join says why with an alert, and the button works again', async () => {
    renderPage('join', true, { join: 'This browser opened another invite link after this page loaded.' });

    const button = screen.getByRole('button', { name: 'Join' });
    expect(screen.getByRole('alert')).toHaveTextContent('This browser opened another invite link after this page loaded.');
    expect(button).toHaveAccessibleDescription('This browser opened another invite link after this page loaded.');
    expect(button).not.toHaveAttribute('aria-busy', 'true');

    await userEvent.click(button);
    expect(router.post).toHaveBeenCalledTimes(1);
  });

  it('a member: the text and a link to the community, no "Join"', () => {
    renderPage('member');

    expect(screen.getByText('You are already a member of this community.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Open the community' })).toHaveAttribute('href', '/communities.show/harbour-of-links');
    expect(screen.queryByRole('button', { name: 'Join' })).toBeNull();
  });

  it('a person whom a manager removed: the text of the block and no button', () => {
    renderPage('blocked');

    expect(screen.getByText('You cannot join this community by yourself. Ask its managers for an invitation.')).toBeInTheDocument();
    expect(screen.queryByRole('button')).toBeNull();
    expect(screen.queryByRole('link', { name: 'Open the community' })).toBeNull();
  });

  it('a link that does not work: one title, one message, and nothing of a community', () => {
    renderPage('unusable');

    expect(screen.getByRole('heading', { level: 1, name: 'This invite link does not work' })).toBeInTheDocument();
    expect(screen.getByText('This invite link does not work. Ask the person who gave it to you for a new one.')).toBeInTheDocument();
    expect(screen.queryByText('Harbour of Links', { exact: false })).toBeNull();
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('has no automated violation (axe), for a visitor and for a person who joins', async () => {
    for (const signedIn of [false, true]) {
      const { container, unmount } = renderPage('join', signedIn);
      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });
      expect(results.violations.map((violation) => violation.id)).toEqual([]);
      unmount();
    }
  });
});
