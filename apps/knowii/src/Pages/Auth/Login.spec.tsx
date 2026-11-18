import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type * as Common from '@knowii/common';
import LoginPage from './Login';

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
        post: vi.fn(),
        reset: vi.fn(),
        processing: false,
        errors: {},
      };
    },
  };
});
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/route/${name}` }));
vi.mock('@/Components/Footer', () => ({ default: () => null }));

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => page,
}));

describe('LoginPage with an OpenID Connect provider (#1385)', () => {
  it('shows the provider above the password, and keeps the other ways', () => {
    page.props = { oidc: { label: 'Example account', error: null, status: null } };
    render(<LoginPage canResetPassword status="" />);

    const provider = screen.getByRole('link', { name: 'Continue with Example account' });
    const email = screen.getByLabelText('Email');
    // The provider comes first in the page
    expect(provider.compareDocumentPosition(email) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Log in' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Email me a sign-in link instead' })).toBeInTheDocument();
  });

  it('shows the error of a failed provider sign-in, once, as an alert', () => {
    page.props = { oidc: { label: 'Example account', error: 'We could not sign you in with this account.', status: null } };
    render(<LoginPage canResetPassword status="" />);

    expect(screen.getAllByRole('alert')).toHaveLength(1);
    expect(screen.getByRole('alert')).toHaveTextContent('We could not sign you in with this account.');
  });

  it('has no provider button without a provider', () => {
    page.props = { oidc: null };
    render(<LoginPage canResetPassword status="" />);

    expect(screen.queryByRole('link', { name: /Continue with/ })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Log in' })).toBeInTheDocument();
  });
});
