import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { JoinCommunity } from './JoinCommunity';

const page = vi.hoisted(() => ({ user: null as { cuid: string } | null }));
const router = vi.hoisted(() => ({ reload: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router,
}));

vi.mock('@knowii/common', async () => ({
  ...(await vi.importActual<Record<string, unknown>>('@knowii/common')),
  useTypedPage: () => ({ props: { auth: { user: page.user } } }),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

beforeEach(() => {
  vi.restoreAllMocks();
  router.reload.mockClear();
});

const community = (changes: Partial<Community> = {}) =>
  ({
    cuid: 'c1',
    name: 'Tests',
    slug: 'tests',
    description: '',
    visibility: 'private',
    join_policy: 'open',
    is_member: false,
    ...changes,
  }) as Community;

describe('JoinCommunity (#1486)', () => {
  it('offers "Sign up to join" and "Sign in" to a visitor of an open community', () => {
    page.user = null;
    render(<JoinCommunity community={community()} />);

    expect(screen.getByRole('link', { name: 'Sign up to join' })).toHaveAttribute('href', '/register');
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login');
    expect(screen.queryByRole('button', { name: 'Join' })).toBeNull();
  });

  it('offers "Join" to a signed-in person who is not a member', () => {
    page.user = { cuid: 'u9' };
    render(<JoinCommunity community={community()} />);

    expect(screen.getByRole('button', { name: 'Join' })).toBeEnabled();
    expect(screen.queryByRole('link', { name: /Sign/ })).toBeNull();
  });

  it('shows nothing for a community by invitation, nor for a member', () => {
    page.user = { cuid: 'u9' };
    const { container, rerender } = render(<JoinCommunity community={community({ join_policy: 'invitation' })} />);
    expect(container).toBeEmptyDOMElement();

    rerender(<JoinCommunity community={community({ is_member: true })} />);
    expect(container).toBeEmptyDOMElement();

    page.user = null;
    rerender(<JoinCommunity community={community({ join_policy: 'invitation' })} />);
    expect(container).toBeEmptyDOMElement();
  });

  it('joins with the keyboard and loads the page again as a member', async () => {
    page.user = { cuid: 'u9' };
    const join = vi.spyOn(knowiiApiClient.communities.members, 'join').mockResolvedValue({ ok: true, message: '', data: community() });
    render(<JoinCommunity community={community()} />);

    screen.getByRole('button', { name: 'Join' }).focus();
    await userEvent.keyboard('{Enter}');

    await waitFor(() => expect(join).toHaveBeenCalledWith('c1'));
    await waitFor(() => expect(router.reload).toHaveBeenCalled());
  });

  it('keeps the focus on the button while it works, and ignores a second press', async () => {
    page.user = { cuid: 'u9' };
    let finish: (value: Awaited<ReturnType<typeof knowiiApiClient.communities.members.join>>) => void = () => undefined;
    const join = vi.spyOn(knowiiApiClient.communities.members, 'join').mockReturnValue(new Promise((resolve) => (finish = resolve)));
    render(<JoinCommunity community={community()} />);

    const button = screen.getByRole('button', { name: 'Join' });
    button.focus();
    await userEvent.keyboard('{Enter}');

    await waitFor(() => expect(button).toHaveAttribute('aria-busy', 'true'));
    expect(button).toHaveFocus();
    expect(button).toBeEnabled();
    await userEvent.keyboard('{Enter}');
    expect(join).toHaveBeenCalledTimes(1);

    finish({ ok: false, status: 422, message: 'No', errors: {} });
    await waitFor(() => expect(button).toHaveAttribute('aria-busy', 'false'));
  });

  it('shows the message of the refusal, and stays', async () => {
    page.user = { cuid: 'u9' };
    vi.spyOn(knowiiApiClient.communities.members, 'join').mockResolvedValue({
      ok: false,
      status: 422,
      message: 'You cannot join this community by yourself. Ask its managers for an invitation.',
      errors: {},
    });
    render(<JoinCommunity community={community()} />);

    await userEvent.click(screen.getByRole('button', { name: 'Join' }));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('You cannot join this community by yourself. Ask its managers for an invitation.');
    expect(screen.getByRole('button', { name: 'Join' })).toHaveAccessibleDescription(alert.textContent ?? '');
    expect(router.reload).not.toHaveBeenCalled();
  });
});
