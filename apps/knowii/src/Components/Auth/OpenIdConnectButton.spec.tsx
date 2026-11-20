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
    page.props = { oidc: { label: 'Example account', error: null, status: null } };
    render(<OpenIdConnectButton />);

    const link = screen.getByRole('link', { name: 'Continue with Example account' });
    expect(link).toHaveAttribute('href', '/route/oidc.redirect');
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('says that a new account is created, with the terms and the minimum age', () => {
    page.props = { oidc: { label: 'Example account', error: null, status: null }, legalPages: ['privacy-policy', 'terms-of-service'] };
    render(<OpenIdConnectButton />);

    expect(screen.getByText(/this creates one/)).toHaveTextContent('you are 16 or older');
    expect(screen.getByRole('link', { name: 'Terms of Service' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Privacy Policy' })).toBeInTheDocument();
  });

  describe('the legal texts of the instance (#1653)', () => {
    const oidc = { label: 'Example account', error: null, status: null };
    const sentence = () => screen.getByText(/this creates one/).textContent;

    it('names the terms and the privacy policy when the instance has both', () => {
      page.props = { oidc, legalPages: ['privacy-policy', 'terms-of-service', 'dpa'] };
      render(<OpenIdConnectButton />);

      expect(sentence()).toBe(
        'If you have no account yet, this creates one: you are 16 or older and you agree to the Terms of Service and Privacy Policy.',
      );
      expect(screen.getByRole('link', { name: 'Terms of Service' })).toHaveAttribute('href', '/route/terms-of-service.show');
      expect(screen.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute('href', '/route/privacy-policy.show');
    });

    it('names only the terms when the instance has no privacy policy', () => {
      page.props = { oidc, legalPages: ['terms-of-service'] };
      render(<OpenIdConnectButton />);

      expect(sentence()).toBe('If you have no account yet, this creates one: you are 16 or older and you agree to the Terms of Service.');
      expect(screen.getByRole('link', { name: 'Terms of Service' })).toHaveAttribute('href', '/route/terms-of-service.show');
      expect(screen.queryByRole('link', { name: 'Privacy Policy' })).toBeNull();
    });

    it('names only the privacy policy when the instance has no terms', () => {
      page.props = { oidc, legalPages: ['privacy-policy', 'imprint'] };
      render(<OpenIdConnectButton />);

      expect(sentence()).toBe('If you have no account yet, this creates one: you are 16 or older and you agree to the Privacy Policy.');
      expect(screen.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute('href', '/route/privacy-policy.show');
      expect(screen.queryByRole('link', { name: 'Terms of Service' })).toBeNull();
    });

    it('keeps the minimum age and names no text when the instance has neither', () => {
      page.props = { oidc, legalPages: ['imprint', 'security'] };
      render(<OpenIdConnectButton />);

      expect(sentence()).toBe('If you have no account yet, this creates one: you are 16 or older.');
      expect(screen.getAllByRole('link').map((link) => link.textContent)).toEqual(['Continue with Example account']);
    });

    it('names no text without the prop', () => {
      page.props = { oidc };
      render(<OpenIdConnectButton />);

      expect(sentence()).toBe('If you have no account yet, this creates one: you are 16 or older.');
      expect(screen.queryByRole('link', { name: 'Terms of Service' })).toBeNull();
      expect(screen.queryByRole('link', { name: 'Privacy Policy' })).toBeNull();
    });
  });

  it('shows the error of a failed sign-in as an alert, above the button', () => {
    page.props = { oidc: { label: 'Example account', error: 'We could not sign you in with this account.', status: null } };
    render(<OpenIdConnectButton />);

    expect(screen.getByRole('alert')).toHaveTextContent('We could not sign you in with this account.');
  });
});
