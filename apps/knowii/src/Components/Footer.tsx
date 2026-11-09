import { CONTACT_URL, metadata, PRIVACY_POLICY_URL, TERMS_OF_SERVICE_URL } from '@knowii/common';
import { FaEnvelope } from 'react-icons/fa';
import { FaXTwitter } from 'react-icons/fa6';
import { Link } from '@inertiajs/react';
import ApplicationName from '@/Components/ApplicationName';
import { buttonClasses } from '@/Components/ui';

const footerLinkClasses = 'text-muted underline-offset-4 transition-colors hover:text-primary hover:underline';

export default function Footer() {
  return (
    <footer className="page-content-boundaries mt-auto grid grid-cols-1 gap-8 rounded-t-xl border border-b-0 border-border bg-well px-4 py-8 md:grid-cols-3 md:px-8">
      <div className="flex flex-col items-center gap-4">
        <ApplicationName className="w-40" />
        <div className="flex flex-row justify-center gap-3">
          <a href={`mailto:${metadata.contact.info}`} aria-label="Contact Knowii by email" className={buttonClasses({ iconOnly: true })}>
            <FaEnvelope aria-hidden="true" />
          </a>
          <a
            href={metadata.social.xSebastien}
            aria-label="dSebastien on X"
            className={buttonClasses({ variant: 'secondary', iconOnly: true })}
          >
            <FaXTwitter aria-hidden="true" />
          </a>
        </div>
        <span className="text-xs text-muted">© {metadata.author}. All rights reserved</span>
      </div>

      <nav aria-labelledby="footer-links" className="flex flex-col items-center gap-2 text-center md:items-start md:text-left">
        <h2 id="footer-links" className="text-lg font-extrabold">
          Links
        </h2>
        <ul className="flex flex-col gap-1 text-sm">
          <li>
            <a className={footerLinkClasses} href={metadata.sourceCodeUrl} target="_blank" rel="noreferrer">
              Source code
            </a>
          </li>
          <li>
            <a className={footerLinkClasses} href={metadata.social.newsletterSebastien} target="_blank" rel="noreferrer">
              Newsletter
            </a>
          </li>
          <li>
            <a className={footerLinkClasses} href={metadata.social.blogSebastien} target="_blank" rel="noreferrer">
              Blog
            </a>
          </li>
          <li>
            <Link className={footerLinkClasses} href={CONTACT_URL} preserveState={true}>
              Contact us
            </Link>
          </li>
        </ul>
      </nav>

      <nav aria-labelledby="footer-legal" className="flex flex-col items-center gap-2 text-center md:items-start md:text-left">
        <h2 id="footer-legal" className="text-lg font-extrabold">
          Legal
        </h2>
        <ul className="flex flex-col gap-1 text-sm">
          <li>
            <Link className={footerLinkClasses} href={route(TERMS_OF_SERVICE_URL)} preserveState={true}>
              Terms of service
            </Link>
          </li>
          <li>
            <Link className={footerLinkClasses} href={route(PRIVACY_POLICY_URL)} preserveState={true}>
              Privacy policy
            </Link>
          </li>
        </ul>
      </nav>
    </footer>
  );
}
