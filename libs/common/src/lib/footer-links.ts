import metadata from './metadata.json';
import { CONTACT_URL, CONTENT_POLICY_URL, DPA_URL, SECURITY_URL, IMPRINT_URL, PRIVACY_POLICY_URL, TERMS_OF_SERVICE_URL } from './constants';
import type { InstancePageProp } from './types/jetstream/jetstream-inertia.intf';

/**
 * A link of the footer (#938): an external URL (`href`), a path of the app (`path`) or a named Laravel route (`routeName`)
 */
export type FooterLink = { label: string; href: string } | { label: string; path: string } | { label: string; routeName: string };

export interface FooterLinkGroup {
  title: string;
  links: readonly FooterLink[];
}

const repositoryFile = (file: string) => `${metadata.repositoryUrl}/blob/main/${file}`;

/**
 * The link columns of the footer, in their order. The links of the project (the repository, its files) are the same on
 * every instance. The "Community" column belongs to the instance (#1653): the links that it configures
 * (INSTANCE_FOOTER_LINKS), then "Contact us" when it gives a contact address. A column without a link is left out.
 */
export function buildFooterLinkGroups(instance?: InstancePageProp | null): FooterLinkGroup[] {
  const communityLinks: FooterLink[] = (instance?.footerLinks ?? []).map((link) => ({ label: link.label, href: link.href }));
  if (instance?.contactEmail) {
    communityLinks.push({ label: 'Contact us', path: CONTACT_URL });
  }

  const groups: FooterLinkGroup[] = [
    {
      title: 'Product',
      links: [
        { label: 'Roadmap', href: `${metadata.repositoryUrl}/milestones` },
        { label: 'Changelog', href: repositoryFile('CHANGELOG.md') },
        { label: 'Self-hosting guide', href: repositoryFile('SELF_HOSTING.md') },
        { label: 'Report a problem', href: `${metadata.repositoryUrl}/issues` },
      ],
    },
    {
      title: 'Community',
      links: communityLinks,
    },
    {
      title: 'Open source',
      links: [
        { label: 'Source code', href: metadata.repositoryUrl },
        { label: 'Licence (AGPL-3.0)', href: repositoryFile('LICENSE') },
        { label: 'Contributing', href: repositoryFile('CONTRIBUTING.md') },
        { label: 'Source First', href: 'https://sourcefirst.com/' },
      ],
    },
    {
      title: 'Legal',
      links: [
        { label: 'Terms of service', routeName: TERMS_OF_SERVICE_URL },
        { label: 'Privacy policy', routeName: PRIVACY_POLICY_URL },
        { label: 'Data processing agreement', routeName: DPA_URL },
        { label: 'Content policy', routeName: CONTENT_POLICY_URL },
        { label: 'Imprint', routeName: IMPRINT_URL },
        { label: 'Security', routeName: SECURITY_URL },
      ],
    },
  ];

  return groups.filter((group) => group.links.length > 0);
}
