import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type * as Common from '@knowii/common';
import OpenIdConnectButton from './OpenIdConnectButton';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => page,
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/route/${name}` }));

describe('OpenIdConnectButton', () => {
  it('shows nothing when the instance has no provider', () => {
    page.props = { oidc: null };
    const { container } = render(<OpenIdConnectButton />);

    expect(container).toBeEmptyDOMElement();
  });

  it('shows nothing when the prop is missing', () => {
    page.props = {};
    const { container } = render(<OpenIdConnectButton />);

    expect(container).toBeEmptyDOMElement();
  });

  it('is a link to the redirect of the provider, named after it', () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    render(<OpenIdConnectButton />);

    const link = screen.getByRole('link', { name: 'Continue with Knowii account' });
    expect(link).toHaveAttribute('href', '/route/oidc.redirect');
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('says that a new account is created, with the terms and the minimum age', () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    render(<OpenIdConnectButton />);

    expect(screen.getByText(/this creates one/)).toHaveTextContent('you are 16 or older');
    expect(screen.getByRole('link', { name: 'Terms of Service' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Privacy Policy' })).toBeInTheDocument();
  });

  it('shows the error of a failed sign-in as an alert, above the button', () => {
    page.props = { oidc: { label: 'Knowii account', error: 'We could not sign you in with this account.', status: null } };
    render(<OpenIdConnectButton />);

    expect(screen.getByRole('alert')).toHaveTextContent('We could not sign you in with this account.');
  });
});
