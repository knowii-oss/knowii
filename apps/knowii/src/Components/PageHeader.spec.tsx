import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { readKeptText, writeKeptText } from '@knowii/common';
import { ApplicationToolkitProvider } from './ApplicationToolkit';
import PageHeader from './PageHeader';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, method: _method, ...props }: { children: React.ReactNode; href: string; method?: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));

describe('PageHeader', () => {
  it('removes every text that the account did not send, and only theirs, on "Log out" (#1622)', async () => {
    const text = { fields: { title: 'Draft', body: 'Not sent' }, changedAt: Date.now(), base: null };
    writeKeptText({ account: 'u1', kind: 'post', place: 's1' }, text);
    writeKeptText({ account: 'u2', kind: 'post', place: 's1' }, text);
    render(
      <PageHeader
        compact
        addLinkOnLogo={false}
        showDashboardButton={false}
        showLoginButton={false}
        showLogoutButton
        showRegisterButton={false}
        accountCuid="u1"
      />,
      // The links of the toolkit are the (mocked) Inertia links, as in main.tsx
      { wrapper: ApplicationToolkitProvider },
    );

    await userEvent.click(screen.getByRole('link', { name: 'Log out' }));

    expect(readKeptText({ account: 'u1', kind: 'post', place: 's1' })).toBeNull();
    expect(readKeptText({ account: 'u2', kind: 'post', place: 's1' })).not.toBeNull();
    window.localStorage.clear();
  });
});
