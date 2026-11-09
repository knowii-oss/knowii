import metadata from './metadata.json';
import { CONTACT_URL, PRIVACY_POLICY_URL, TERMS_OF_SERVICE_URL } from './constants';

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
 * The link columns of the footer. One list for the whole app: a self-hosted instance changes its footer here.
 * The legal pages that do not exist yet (imprint #903, DPA #904, content policy #902) are added with their page.
 */
export const FOOTER_LINK_GROUPS: readonly FooterLinkGroup[] = [
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
    links: [
      { label: 'Knowii Community', href: metadata.communityUrl },
      { label: 'Blog', href: metadata.social.blogSebastien },
      { label: 'Contact us', path: CONTACT_URL },
    ],
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
    ],
  },
];
