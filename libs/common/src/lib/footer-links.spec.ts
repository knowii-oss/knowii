import { describe, expect, it } from 'vitest';
import { LEGAL_PAGE_SLUGS } from './constants';
import type { LegalPageSlug } from './constants';
import { buildFooterLinkGroups } from './footer-links';
import type { InstancePageProp } from './types/jetstream/jetstream-inertia.intf';
import metadata from './metadata.json';

function instanceValues(values: Partial<InstancePageProp> = {}): InstancePageProp {
  return { contactEmail: 'contact@example.com', publisher: null, footerNote: null, footerLinks: [], socialLinks: [], ...values };
}

const legalLinksOf = (legalPages?: readonly LegalPageSlug[] | null) =>
  buildFooterLinkGroups(instanceValues(), legalPages).find((group) => group.title === 'Legal')?.links;

describe('buildFooterLinkGroups (#1653)', () => {
  it('returns the four columns in their order', () => {
    expect(buildFooterLinkGroups(instanceValues(), LEGAL_PAGE_SLUGS).map((group) => group.title)).toEqual([
      'Product',
      'Community',
      'Open source',
      'Legal',
    ]);
  });

  it('puts the configured links in the "Community" column, then "Contact us"', () => {
    const groups = buildFooterLinkGroups(
      instanceValues({
        footerLinks: [
          { label: 'Example Community', href: 'https://community.example.com' },
          { label: 'Blog', href: 'https://blog.example.com' },
        ],
      }),
    );

    expect(groups.find((group) => group.title === 'Community')?.links).toEqual([
      { label: 'Example Community', href: 'https://community.example.com' },
      { label: 'Blog', href: 'https://blog.example.com' },
      { label: 'Contact us', path: '/contact' },
    ]);
  });

  it('shows "Contact us" alone when the instance configures no link', () => {
    expect(buildFooterLinkGroups(instanceValues()).find((group) => group.title === 'Community')?.links).toEqual([
      { label: 'Contact us', path: '/contact' },
    ]);
  });

  it('leaves "Contact us" out without a contact address', () => {
    const groups = buildFooterLinkGroups(
      instanceValues({ contactEmail: '', footerLinks: [{ label: 'Blog', href: 'https://blog.example.com' }] }),
    );

    expect(groups.find((group) => group.title === 'Community')?.links).toEqual([{ label: 'Blog', href: 'https://blog.example.com' }]);
  });

  it('leaves a column without a link out', () => {
    for (const instance of [instanceValues({ contactEmail: '' }), null, undefined]) {
      expect(buildFooterLinkGroups(instance, LEGAL_PAGE_SLUGS).map((group) => group.title)).toEqual(['Product', 'Open source', 'Legal']);
    }
  });

  describe('the "Legal" column', () => {
    it('holds every legal page of an instance that has every text, in the order of the footer', () => {
      expect(legalLinksOf(LEGAL_PAGE_SLUGS)).toEqual([
        { label: 'Terms of service', routeName: 'terms-of-service.show' },
        { label: 'Privacy policy', routeName: 'privacy-policy.show' },
        { label: 'Data processing agreement', routeName: 'dpa.show' },
        { label: 'Content policy', routeName: 'content-policy.show' },
        { label: 'Imprint', routeName: 'imprint.show' },
        { label: 'Security', routeName: 'security.show' },
      ]);
    });

    it('holds only the pages that exist, in the order of the footer and not in the order of the list', () => {
      expect(legalLinksOf(['security', 'privacy-policy'])).toEqual([
        { label: 'Privacy policy', routeName: 'privacy-policy.show' },
        { label: 'Security', routeName: 'security.show' },
      ]);
      expect(legalLinksOf(['imprint'])).toEqual([{ label: 'Imprint', routeName: 'imprint.show' }]);
    });

    it('has a link for each slug of a legal page', () => {
      for (const page of LEGAL_PAGE_SLUGS) {
        expect(legalLinksOf([page])).toEqual([expect.objectContaining({ routeName: `${page}.show` })]);
      }
    });

    it('is left out when the instance has no legal text, and without the list', () => {
      for (const legalPages of [[], null, undefined]) {
        expect(legalLinksOf(legalPages)).toBeUndefined();
        expect(buildFooterLinkGroups(instanceValues(), legalPages).map((group) => group.title)).toEqual([
          'Product',
          'Community',
          'Open source',
        ]);
      }
      expect(buildFooterLinkGroups().map((group) => group.title)).toEqual(['Product', 'Open source']);
    });
  });

  it('builds the links of the project from the address of the repository, the only key of the metadata', () => {
    expect(Object.keys(metadata)).toEqual(['repositoryUrl']);

    const groups = buildFooterLinkGroups();
    expect(groups.find((group) => group.title === 'Open source')?.links[0]).toEqual({
      label: 'Source code',
      href: 'https://github.com/knowii-oss/knowii',
    });
    expect(groups.find((group) => group.title === 'Product')?.links).toContainEqual({
      label: 'Self-hosting guide',
      href: 'https://github.com/knowii-oss/knowii/blob/main/SELF_HOSTING.md',
    });
  });
});
