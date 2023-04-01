import Head from 'next/head';
import React, { PropsWithChildren } from 'react';
//import NavBar from './nav-bar';
import Footer from './footer';
import { propertiesOf } from '@knowii/common';
import { useRouter } from 'next/router';
import { NavBar } from './nav-bar';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const site = require('../../../../libs/common/src/lib/metadata.json').siteName;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthor = require('../../../../libs/common/src/lib/metadata.json').author;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorAlias = require('../../../../libs/common/src/lib/metadata.json').authorAlias;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteDescription = require('../../../../libs/common/src/lib/metadata.json').description;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteKeywords = require('../../../../libs/common/src/lib/metadata.json').keywords;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteTitle = require('../../../../libs/common/src/lib/metadata.json').title;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteUrl = require('../../../../libs/common/src/lib/metadata.json').url;

/**
 * Page metadata that pages can customize
 * WARNING: Whenever this changes, the for loop below processing custom meta MUST be adapted
 */
export interface SupportedMeta {
  siteName: string;
  author?: string;
  authorAlias?: string;
  image: string;
  title: string;
  description: string;
  type: string;
  date?: string;
  keywords?: string;
  canonicalUrl?: string;
}

const supportedMetaProperties = propertiesOf<SupportedMeta>();

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface LayoutProps
  extends PropsWithChildren<{
    customMeta?: Partial<SupportedMeta>;
  }> {}

export function Layout({ children, customMeta }: LayoutProps) {
  const router = useRouter();

  console.log(router.asPath);

  /**
   * Default metadata
   */
  const meta: SupportedMeta = {
    siteName: site,
    author: siteAuthor,
    authorAlias: siteAuthorAlias,
    title: siteTitle,
    description: siteDescription,
    image: `/images/blog/sebastien-avatar.jpeg`, // FIXME replace with a real banner
    type: 'website',
    canonicalUrl: `${siteUrl}${router.asPath}`,
    keywords: siteKeywords.join(),
  };

  if (customMeta) {
    for (const customMetaKey of Object.keys(customMeta ? customMeta : [])) {
      let propertyHandled = false;

      if (supportedMetaProperties('title') === customMetaKey) {
        if (customMeta.title && customMeta.title.trim().length > 0) {
          meta.title = customMeta.title;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('date') === customMetaKey) {
        if (customMeta.date && customMeta.date.trim().length > 0) {
          meta.date = customMeta.date;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('description') === customMetaKey) {
        if (customMeta.description && customMeta.description.trim().length > 0) {
          meta.description = customMeta.description;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('type') === customMetaKey) {
        if (customMeta.type && customMeta.type.trim().length > 0) {
          meta.type = customMeta.type;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('image') === customMetaKey) {
        if (customMeta.image && customMeta.image.trim().length > 0) {
          meta.image = customMeta.image;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('keywords') === customMetaKey) {
        if (customMeta.keywords && customMeta.keywords.trim().length > 0) {
          meta.keywords = customMeta.keywords;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('canonicalUrl') === customMetaKey) {
        if (customMeta.canonicalUrl && customMeta.canonicalUrl.trim().length > 0) {
          meta.canonicalUrl = customMeta.canonicalUrl;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('siteName') === customMetaKey) {
        if (customMeta.siteName && customMeta.siteName.trim().length > 0) {
          meta.siteName = customMeta.siteName;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('author') === customMetaKey) {
        if (customMeta.author && customMeta.author.trim().length > 0) {
          meta.author = customMeta.author;
        }
        propertyHandled = true;
      }

      if (supportedMetaProperties('authorAlias') === customMetaKey) {
        if (customMeta.authorAlias && customMeta.authorAlias.trim().length > 0) {
          meta.authorAlias = customMeta.authorAlias;
        }
        propertyHandled = true;
      }

      if (!propertyHandled) {
        throw new Error(`Unhandled meta property: ${customMetaKey}`);
      }
    }
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
        <meta name="description" content={meta.description} />
        <meta name="keywords" content={meta.keywords} />
        <meta property="og:url" content={`${siteUrl}${router.asPath}`} />
        <link rel="canonical" href={meta.canonicalUrl} />
        <meta property="og:type" content={meta.type} />
        <meta property="og:site_name" content={meta.siteName} />
        <meta property="og:description" content={meta.description} />
        <meta property="og:title" content={meta.title} />
        <meta property="og:image" content={meta.image} />
        <meta property="og:locale" content="en_US" />
        <meta property="image" content={meta.image} />
        <meta name="author" content={meta.author} />
        <meta property="article:author" content={meta.author} />
        <meta name="twitter:creator" content={meta.authorAlias} />
        <meta name="twitter:site" content={meta.authorAlias} />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content={meta.title} />
        <meta name="twitter:description" content={meta.description} />
        <meta name="twitter:image" content={meta.image} />
        {meta.date && <meta property="article:published_time" content={meta.date} />}
      </Head>
      <a href="#main" aria-label="Skip to the content" className="sr-only focus:not-sr-only">
        Skip to content
      </a>
      <NavBar />

      <main id="main" className="relative pt-20 min-h-screen">
        {children}
      </main>

      <footer>
        <Footer />
      </footer>
    </>
  );
}

export default Layout;
