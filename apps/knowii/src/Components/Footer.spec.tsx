import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Footer from './Footer';

const pageProps: { newsletter?: { enabled: boolean; turnstileSiteKey: string | null }; auth: { user: { email: string } | null } } = {
  auth: { user: null },
};

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: pageProps }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
// The form has its own spec: here only its presence matters
vi.mock('@/Components/NewsletterForm', () => ({
  default: ({ defaultEmail }: { defaultEmail?: string }) => <form aria-label="Newsletter" data-email={defaultEmail ?? ''} />,
}));

describe('Footer', () => {
  beforeEach(() => {
    delete pageProps.newsletter;
    pageProps.auth.user = null;
  });

  it('shows one navigation landmark per link column (#938)', () => {
    render(<Footer />);

    expect(screen.getByRole('contentinfo')).toBeInTheDocument();
    for (const name of ['Product', 'Community', 'Open source', 'Legal']) {
      expect(screen.getByRole('navigation', { name })).toBeInTheDocument();
    }
    const legal = screen.getByRole('navigation', { name: 'Legal' });
    expect(within(legal).getByRole('link', { name: 'Privacy policy' })).toHaveAttribute('href', '/privacy-policy.show');
    expect(screen.getByRole('link', { name: 'Contact us' })).toHaveAttribute('href', '/contact');
    expect(screen.getByRole('link', { name: 'Source First' })).toHaveAttribute('href', 'https://sourcefirst.com/');
  });

  it('hides the newsletter form while the instance configures no list', () => {
    pageProps.newsletter = { enabled: false, turnstileSiteKey: null };
    render(<Footer />);

    expect(screen.queryByRole('form', { name: 'Newsletter' })).toBeNull();
  });

  it('shows the newsletter form with the address of the signed-in member', () => {
    pageProps.newsletter = { enabled: true, turnstileSiteKey: 'site-key' };
    pageProps.auth.user = { email: 'ada@example.com' };
    render(<Footer />);

    expect(screen.getByRole('form', { name: 'Newsletter' })).toHaveAttribute('data-email', 'ada@example.com');
  });

  it('shows only the bottom line and the legal links in the compact variant', () => {
    pageProps.newsletter = { enabled: true, turnstileSiteKey: 'site-key' };
    render(<Footer variant="compact" />);

    expect(screen.getByRole('navigation', { name: 'Legal' })).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Product' })).toBeNull();
    expect(screen.queryByRole('form', { name: 'Newsletter' })).toBeNull();
    expect(screen.getByText(/Made in Belgium/)).toBeInTheDocument();
  });
});
