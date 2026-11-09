import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as Common from '@knowii/common';
import type * as Ui from '@/Components/ui';
import RegisterPage from './Register';

const posts: Array<[string, Record<string, unknown>]> = [];

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    Head: () => null,
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
    useForm: (initial: Record<string, unknown>) => {
      const [data, setState] = React.useState(initial);
      return {
        data,
        setData: (key: string, value: unknown) => setState((current) => ({ ...current, [key]: value })),
        post: (url: string) => posts.push([url, data]),
        reset: vi.fn(),
        processing: false,
        errors: {},
      };
    },
  };
});
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@/Components/FooterGuest', () => ({ default: () => null }));
vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => ({ props: { jetstream: { hasTermsAndPrivacyPolicyFeature: false } } }),
}));
// The widget of Cloudflare cannot load in tests: a button stands for a solved check
vi.mock('@/Components/ui', async (importOriginal) => ({
  ...(await importOriginal<typeof Ui>()),
  Turnstile: ({ onTokenChange }: { onTokenChange: (token: string | null) => void }) => (
    <button type="button" onClick={() => onTokenChange('turnstile-ok')}>
      Solve the bot check
    </button>
  ),
}));

describe('RegisterPage', () => {
  it('waits for the bot check, then sends its token (#737)', async () => {
    render(<RegisterPage turnstileSiteKey="site-key" />);

    await userEvent.type(screen.getByLabelText('Name'), 'Grace Hopper');
    await userEvent.type(screen.getByLabelText('Email'), 'grace@example.com');
    await userEvent.type(screen.getByLabelText('Password', { exact: true }), 'A-strong-password-2026!');
    await userEvent.type(screen.getByLabelText('Confirm password'), 'A-strong-password-2026!');
    expect(screen.getByRole('button', { name: 'Register' })).toBeDisabled();
    await userEvent.click(screen.getByRole('button', { name: 'Solve the bot check' }));
    expect(screen.getByRole('button', { name: 'Register' })).toBeEnabled();
    await userEvent.click(screen.getByRole('button', { name: 'Register' }));

    expect(posts.at(-1)?.[1]).toMatchObject({ turnstile_token: 'turnstile-ok', website: '' });
  });

  it('has no bot check without Turnstile keys, and hides the honeypot (#737)', () => {
    render(<RegisterPage turnstileSiteKey={null} />);

    expect(screen.queryByRole('button', { name: 'Solve the bot check' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Register' })).toBeEnabled();
    expect(document.querySelector('[aria-hidden="true"] input[name="website"]')).not.toBeNull();
  });
});
