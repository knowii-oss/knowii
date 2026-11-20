import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { InstancePageProp, LegalPageSlug } from '@knowii/common';
import { LEGAL_PAGE_SLUGS } from '@knowii/common';
import Footer from './Footer';

const pageProps: {
  newsletter?: { enabled: boolean; turnstileSiteKey: string | null };
  instance?: InstancePageProp;
  legalPages?: LegalPageSlug[];
  auth: { user: { email: string } | null };
} = {
  auth: { user: null },
};

function instanceValues(values: Partial<InstancePageProp> = {}): InstancePageProp {
  return { contactEmail: 'contact@example.com', publisher: null, footerNote: null, footerLinks: [], socialLinks: [], ...values };
}

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
    pageProps.instance = instanceValues();
    // An instance with every legal text, unless a test says otherwise
    pageProps.legalPages = [...LEGAL_PAGE_SLUGS];
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
    expect(within(legal).getByRole('link', { name: 'Data processing agreement' })).toHaveAttribute('href', '/dpa.show');
    expect(within(legal).getByRole('link', { name: 'Imprint' })).toHaveAttribute('href', '/imprint.show');
    expect(within(legal).getByRole('link', { name: 'Content policy' })).toHaveAttribute('href', '/content-policy.show');
    expect(within(legal).getByRole('link', { name: 'Security' })).toHaveAttribute('href', '/security.show');
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
    pageProps.instance = instanceValues({ publisher: 'Example Ltd', footerNote: 'Made in Ireland.' });
    render(<Footer variant="compact" />);

    expect(screen.getByRole('navigation', { name: 'Legal' })).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Product' })).toBeNull();
    expect(screen.queryByRole('form', { name: 'Newsletter' })).toBeNull();
    expect(screen.getByText(`© ${new Date().getFullYear()} Example Ltd. Made in Ireland.`)).toBeInTheDocument();
  });

  describe('the legal pages of the instance (#1653)', () => {
    const legalLinks = () => within(screen.getByRole('navigation', { name: 'Legal' })).getAllByRole('link');

    it('links every legal page of an instance that has every text', () => {
      render(<Footer />);

      expect(legalLinks().map((link) => [link.textContent, link.getAttribute('href')])).toEqual([
        ['Terms of service', '/terms-of-service.show'],
        ['Privacy policy', '/privacy-policy.show'],
        ['Data processing agreement', '/dpa.show'],
        ['Content policy', '/content-policy.show'],
        ['Imprint', '/imprint.show'],
        ['Security', '/security.show'],
      ]);
    });

    it('links only the legal pages that exist', () => {
      pageProps.legalPages = ['privacy-policy', 'imprint'];
      render(<Footer />);

      expect(legalLinks().map((link) => link.textContent)).toEqual(['Privacy policy', 'Imprint']);
      expect(screen.queryByRole('link', { name: 'Terms of service' })).toBeNull();
      expect(screen.queryByRole('link', { name: 'Data processing agreement' })).toBeNull();
      expect(screen.queryByRole('link', { name: 'Content policy' })).toBeNull();
      expect(screen.queryByRole('link', { name: 'Security' })).toBeNull();
    });

    it('has no "Legal" column when the instance has no legal text, and keeps the other columns', () => {
      pageProps.legalPages = [];
      render(<Footer />);

      expect(screen.queryByRole('navigation', { name: 'Legal' })).toBeNull();
      expect(screen.queryByRole('link', { name: 'Privacy policy' })).toBeNull();
      for (const name of ['Product', 'Community', 'Open source']) {
        expect(screen.getByRole('navigation', { name })).toBeInTheDocument();
      }
    });

    it('has no "Legal" column without the prop', () => {
      delete pageProps.legalPages;
      render(<Footer />);

      expect(screen.queryByRole('navigation', { name: 'Legal' })).toBeNull();
      expect(screen.queryByRole('link', { name: 'Imprint' })).toBeNull();
    });

    it('follows in the compact variant: only the pages that exist', () => {
      pageProps.legalPages = ['terms-of-service', 'security'];
      render(<Footer variant="compact" />);

      expect(legalLinks().map((link) => [link.textContent, link.getAttribute('href')])).toEqual([
        ['Terms of service', '/terms-of-service.show'],
        ['Security', '/security.show'],
      ]);
    });

    it('shows no legal navigation in the compact variant without a legal text, and keeps the bottom line', () => {
      pageProps.instance = instanceValues({ publisher: 'Example Ltd' });
      for (const legalPages of [[], undefined]) {
        if (legalPages) {
          pageProps.legalPages = legalPages;
        } else {
          delete pageProps.legalPages;
        }
        const { unmount } = render(<Footer variant="compact" />);

        expect(screen.queryByRole('navigation')).toBeNull();
        expect(screen.queryAllByRole('link')).toEqual([]);
        expect(screen.getByText(`© ${new Date().getFullYear()} Example Ltd.`)).toBeInTheDocument();
        unmount();
      }
    });
  });

  describe('the values of the instance (#1653)', () => {
    it('shows the publisher and the note of the instance in the bottom line', () => {
      pageProps.instance = instanceValues({ publisher: 'Example Ltd', footerNote: 'Made in Ireland.' });
      render(<Footer />);

      expect(screen.getByText(`© ${new Date().getFullYear()} Example Ltd. Made in Ireland.`)).toBeInTheDocument();
    });

    it('shows the publisher alone, or the note alone', () => {
      pageProps.instance = instanceValues({ publisher: 'Example Ltd' });
      const { unmount } = render(<Footer />);
      expect(screen.getByText(`© ${new Date().getFullYear()} Example Ltd.`)).toBeInTheDocument();
      unmount();

      pageProps.instance = instanceValues({ footerNote: 'Made in Ireland.' });
      render(<Footer />);
      expect(screen.getByText('Made in Ireland.')).toBeInTheDocument();
      expect(screen.queryByText(/©/)).toBeNull();
    });

    it('shows no bottom line when the instance names no publisher and gives no note', () => {
      const { container } = render(<Footer />);

      expect(screen.queryByText(/©/)).toBeNull();
      expect(container.querySelector('p.text-xs')).toBeNull();
    });

    it('shows the configured links in the "Community" column, then "Contact us"', () => {
      pageProps.instance = instanceValues({
        footerLinks: [
          { label: 'Example Community', href: 'https://community.example.com' },
          { label: 'Blog', href: 'https://blog.example.com' },
        ],
      });
      render(<Footer />);

      const links = within(screen.getByRole('navigation', { name: 'Community' })).getAllByRole('link');
      expect(links.map((link) => [link.textContent, link.getAttribute('href')])).toEqual([
        ['Example Community', 'https://community.example.com'],
        ['Blog', 'https://blog.example.com'],
        ['Contact us', '/contact'],
      ]);
    });

    it('hides "Contact us" and the mail icon when the instance gives no contact address', () => {
      pageProps.instance = instanceValues({ contactEmail: '', footerLinks: [{ label: 'Blog', href: 'https://blog.example.com' }] });
      render(<Footer />);

      expect(screen.queryByRole('link', { name: 'Contact us' })).toBeNull();
      expect(screen.queryByRole('link', { name: 'Email Knowii' })).toBeNull();
      expect(within(screen.getByRole('navigation', { name: 'Community' })).getByRole('link', { name: 'Blog' })).toBeInTheDocument();
    });

    it('shows the mail icon with the contact address and the repository icon of the project', () => {
      render(<Footer />);

      expect(screen.getByRole('link', { name: 'Email Knowii' })).toHaveAttribute('href', 'mailto:contact@example.com');
      expect(screen.getByRole('link', { name: 'Knowii on GitHub' })).toHaveAttribute('href', 'https://github.com/knowii-oss/knowii');
    });

    it('shows one icon link per social link, named after its service', () => {
      pageProps.instance = instanceValues({
        socialLinks: [
          { property: 'social_link_linkedin', href: 'https://social.example.com/example' },
          { property: 'social_link_youtube', href: 'https://video.example.com/example' },
        ],
      });
      render(<Footer />);

      expect(screen.getByRole('link', { name: 'LinkedIn' })).toHaveAttribute('href', 'https://social.example.com/example');
      expect(screen.getByRole('link', { name: 'YouTube' })).toHaveAttribute('href', 'https://video.example.com/example');
      expect(screen.queryByRole('link', { name: 'X (Twitter)' })).toBeNull();
    });

    it('shows nothing of an instance without the prop: no bottom line, no mail icon, no social icon, no "Community" column', () => {
      delete pageProps.instance;
      render(<Footer />);

      expect(screen.queryByText(/©/)).toBeNull();
      expect(screen.queryByRole('link', { name: 'Email Knowii' })).toBeNull();
      expect(screen.queryByRole('link', { name: 'Contact us' })).toBeNull();
      expect(screen.queryByRole('navigation', { name: 'Community' })).toBeNull();
      // The project stays: its repository icon and its columns
      expect(screen.getByRole('link', { name: 'Knowii on GitHub' })).toBeInTheDocument();
      for (const name of ['Product', 'Open source', 'Legal']) {
        expect(screen.getByRole('navigation', { name })).toBeInTheDocument();
      }
      const brandIcons = screen.getAllByRole('link').filter((link) => link.getAttribute('aria-label') !== null);
      expect(brandIcons.map((link) => link.getAttribute('aria-label'))).toEqual(['Knowii on GitHub']);
    });
  });
});
