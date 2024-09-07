import { metadata, PRIVACY_POLICY_URL, TERMS_OF_SERVICE_URL } from '@knowii/common';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from 'primereact/button';
import { FaEnvelope } from 'react-icons/fa';
import { FaX } from 'react-icons/fa6';
import { Link } from '@inertiajs/react';

export default function Footer() {
  return (
    <footer className="page-content-boundaries bg-gray-800 grid grid-rows-3 md:grid-rows-1 md:grid-cols-3 gap-8 min-h-[16rem] rounded-t-md p-4 text-white pt-8">
      <div className="flex flex-col gap-4 items-center">
        <ApplicationLogo />
        <div className="flex flex-row gap-4 justify-center">
          <a href={`mailto:${metadata.contact.info}`}>
            <Button aria-label="Contact Knowii">
              <FaEnvelope />
            </Button>
          </a>
          <a className="" href={metadata.social.xSebastien}>
            <Button severity="secondary" aria-label="dSebastien on X">
              <FaX />
            </Button>
          </a>
        </div>
        <div>
          <span className="text-xs">© {metadata.author}. All rights reserved</span>
        </div>
      </div>

      <div className="flex flex-col gap-2 items-center text-center md:text-left">
        <div className="mb-3 text-xl font-bold mt-4 w-full">Links</div>
        <ul className="text-sm w-full">
          <li>
            <a className="hover:text-primary-500" href={metadata.sourceCodeUrl} target="_blank" rel="noreferrer">
              Source code
            </a>
          </li>
          <li>
            <a className="hover:text-primary-500" href={metadata.social.newsletterSebastien} target="_blank" rel="noreferrer">
              Newsletter
            </a>
          </li>
          <li>
            <a className="hover:text-primary-500" href={metadata.social.blogSebastien} target="_blank" rel="noreferrer">
              Blog
            </a>
          </li>
        </ul>
      </div>

      <div className="flex flex-col gap-2 items-center text-center md:text-left">
        <div className="mb-3 text-xl font-bold mt-4 w-full">Legal</div>
        <ul className="text-sm w-full">
          <li>
            <Link className="hover:text-primary-500" href={route(TERMS_OF_SERVICE_URL)}>
              Terms of service
            </Link>
          </li>
          <li>
            <Link className="hover:text-primary-500" href={route(PRIVACY_POLICY_URL)}>
              Privacy policy
            </Link>
          </li>
        </ul>
      </div>
    </footer>
  );
}
