import { describe, expect, it } from 'vitest';
import { buildFooterLinkGroups } from './footer-links';
import type { InstancePageProp } from './types/jetstream/jetstream-inertia.intf';
import metadata from './metadata.json';

function instanceValues(values: Partial<InstancePageProp> = {}): InstancePageProp {
  return { contactEmail: 'contact@example.com', publisher: null, footerNote: null, footerLinks: [], socialLinks: [], ...values };
}

describe('buildFooterLinkGroups (#1653)', () => {
  it('returns the four columns in their order', () => {
    expect(buildFooterLinkGroups(instanceValues()).map((group) => group.title)).toEqual(['Product', 'Community', 'Open source', 'Legal']);
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
      expect(buildFooterLinkGroups(instance).map((group) => group.title)).toEqual(['Product', 'Open source', 'Legal']);
    }
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
