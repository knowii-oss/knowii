import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { PresenceMember } from '@knowii/common';
import { HereNowButton } from './HereNowButton';
import { OnlineMembers } from './OnlineMembers';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: () => '/member', writable: true, configurable: true });
});

const members: PresenceMember[] = Array.from({ length: 25 }, (_, index) => ({
  cuid: `m${index}`,
  name: `Member ${String(index).padStart(2, '0')}`,
  username: `member${index}`,
  avatar_url: null,
}));

describe('Online lists in a side panel (#1299)', () => {
  it('keeps "Here now" to one button, opens the full list, filters it, and gives the focus back on Escape', async () => {
    render(<HereNowButton communitySlug="tests" members={members} />);

    const button = screen.getByRole('button', { name: '25 members here, show the list' });
    await userEvent.click(button);

    const panel = await screen.findByRole('dialog', { name: 'Here now (25)' });
    expect(within(panel).getAllByRole('link')).toHaveLength(25);

    await userEvent.type(within(panel).getByLabelText('Find a member'), 'member 07');
    expect(
      within(panel)
        .getAllByRole('link')
        .map((link) => link.textContent),
    ).toEqual([expect.stringContaining('Member 07')]);

    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    expect(button).toHaveFocus();
  });

  it('turns "and N more" under the navigation into a button that shows everybody', async () => {
    render(<OnlineMembers communitySlug="tests" members={members} hidden={false} headingId="online" />);

    expect(within(screen.getByRole('list', { name: 'Online now (25)' })).getAllByRole('link')).toHaveLength(12);
    await userEvent.click(screen.getByRole('button', { name: 'and 13 more: show everybody online' }));

    const panel = await screen.findByRole('dialog', { name: 'Online now (25)' });
    expect(within(panel).getAllByRole('link')).toHaveLength(25);
  });
});
