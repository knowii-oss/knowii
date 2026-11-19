import { Link } from '@inertiajs/react';
import { FaEnvelope } from 'react-icons/fa';
import { FaGithub } from 'react-icons/fa6';
import { useRoute } from 'ziggy-js';
import type { FooterLink } from '@knowii/common';
import { SOCIAL_MEDIA_LINK_ICONS, SOCIAL_MEDIA_LINK_NAMES, buildFooterLinkGroups, metadata, useTypedPage } from '@knowii/common';
import ApplicationName from '@/Components/ApplicationName';
import NewsletterForm from '@/Components/NewsletterForm';
import { buttonClasses, cn } from '@/Components/ui';

const footerLinkClasses = 'touch-target text-sm text-muted underline-offset-4 transition-colors hover:text-primary hover:underline';

interface Props {
  /**
   * full (default): the brand column, the newsletter form and the link columns. compact: the bottom line with the legal
   * links, for the sign-in, legal and error pages.
   */
  variant?: 'full' | 'compact';
}

function FooterLinkItem({ link }: { link: FooterLink }) {
  const route = useRoute();
  if ('href' in link) {
    return (
      <a className={footerLinkClasses} href={link.href}>
        {link.label}
      </a>
    );
  }
  return (
    <Link className={footerLinkClasses} href={'path' in link ? link.path : route(link.routeName)}>
      {link.label}
    </Link>
  );
}

function slug(title: string): string {
  return title.toLowerCase().replace(/[^a-z0-9]+/g, '-');
}

/**
 * The footer of every page (#938): a brand column, the newsletter form when the instance configures a list, and the link
 * columns of buildFooterLinkGroups (libs/common). Each column is a navigation landmark with its own name.
 *
 * What names one instance comes from the shared prop `instance` (#1653): the publisher and the note of the bottom line,
 * the mail icon (the contact address), the social icons and the links of the "Community" column. Each one is hidden when
 * the instance does not set it, and all of them without the prop. The repository icon is the one of the project: it stays.
 */
export default function Footer({ variant = 'full' }: Props) {
  const page = useTypedPage();
  const newsletter = page.props.newsletter;
  const year = new Date().getFullYear();
  const instance = page.props.instance;
  const linkGroups = buildFooterLinkGroups(instance);
  const legal = linkGroups.find((group) => group.title === 'Legal');
  const contactEmail = instance?.contactEmail ?? '';

  // "© <year> <publisher>." then the note of the instance: no line when the instance sets neither
  const bottomLineText = [instance?.publisher ? `© ${year} ${instance.publisher}.` : null, instance?.footerNote ?? null]
    .filter((part): part is string => !!part)
    .join(' ');
  const bottomLine = bottomLineText !== '' ? <p className="text-xs text-muted">{bottomLineText}</p> : null;

  if (variant === 'compact') {
    return (
      <footer className="page-container mt-auto py-6">
        <div className="flex flex-col items-center gap-2 border-t border-border pt-4 sm:flex-row sm:justify-between">
          {bottomLine}
          {legal && (
            <nav aria-label="Legal">
              <ul className="flex flex-wrap justify-center gap-x-4">
                {legal.links.map((link) => (
                  <li key={link.label}>
                    <FooterLinkItem link={link} />
                  </li>
                ))}
              </ul>
            </nav>
          )}
        </div>
      </footer>
    );
  }

  return (
    <footer className="page-container mt-auto">
      <div className="rounded-t-xl border border-b-0 border-border bg-well px-4 py-8 md:px-8">
        <div className="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,4fr)]">
          <div className="flex flex-col gap-4">
            <ApplicationName className="w-40" />
            <p className="text-sm text-muted">A place for your community&apos;s knowledge, ideas and inspiration.</p>
            <div className="flex flex-row gap-3">
              {contactEmail !== '' && (
                <a href={`mailto:${contactEmail}`} aria-label="Email Knowii" className={buttonClasses({ iconOnly: true })}>
                  <FaEnvelope aria-hidden="true" />
                </a>
              )}
              <a
                href={metadata.repositoryUrl}
                aria-label="Knowii on GitHub"
                className={buttonClasses({ variant: 'secondary', iconOnly: true })}
              >
                <FaGithub aria-hidden="true" />
              </a>
              {(instance?.socialLinks ?? []).map((link) => {
                const Icon = SOCIAL_MEDIA_LINK_ICONS[link.property];
                return (
                  <a
                    key={`${link.property}-${link.href}`}
                    href={link.href}
                    aria-label={SOCIAL_MEDIA_LINK_NAMES[link.property]}
                    className={buttonClasses({ variant: 'secondary', iconOnly: true })}
                  >
                    <Icon aria-hidden="true" />
                  </a>
                );
              })}
            </div>
            {newsletter?.enabled && newsletter.turnstileSiteKey && (
              <div className="max-w-md">
                <NewsletterForm turnstileSiteKey={newsletter.turnstileSiteKey} defaultEmail={page.props.auth.user?.email} />
              </div>
            )}
          </div>

          <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
            {linkGroups.map((group) => {
              const titleId = `footer-${slug(group.title)}`;
              return (
                <nav key={group.title} aria-labelledby={titleId} className="flex flex-col gap-2">
                  <h2 id={titleId} className="text-sm font-extrabold">
                    {group.title}
                  </h2>
                  <ul className="flex flex-col md:gap-2">
                    {group.links.map((link) => (
                      <li key={link.label}>
                        <FooterLinkItem link={link} />
                      </li>
                    ))}
                  </ul>
                </nav>
              );
            })}
          </div>
        </div>

        {bottomLine && <div className={cn('mt-8 border-t border-border pt-4')}>{bottomLine}</div>}
      </div>
    </footer>
  );
}
