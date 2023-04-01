import React, { ReactElement } from 'react';
import Document, { Html, Head, Main, NextScript, DocumentContext, DocumentInitialProps } from 'next/document';
import { ColorModeScript } from '@chakra-ui/react';
import { customTheme } from '../chakra-ui.config';
import { IS_BROWSER, SITE_AUTHOR_MICRODATA } from '@knowii/common';
import Script from 'next/script';
import { i18nConfig } from '../../../i18n.config.mjs';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteDescription = require('../../../libs/common/src/lib/metadata.json').description;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteDomain = require('../../../libs/common/src/lib/metadata.json').domain;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteName = require('../../../libs/common/src/lib/metadata.json').siteName;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteTitle = require('../../../libs/common/src/lib/metadata.json').title;

const siteMicrodata = {
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  name: siteName,
  alternateName: siteTitle,
  description: siteDescription,
  publisher: SITE_AUTHOR_MICRODATA,
  url: IS_BROWSER ? window.location.origin : '',
};

/**
 * Custom HTML document
 */
export default class CustomDocument extends Document<{
  styleTags: ReactElement[];
}> {
  static override getInitialProps = async (ctx: DocumentContext): Promise<DocumentInitialProps> => {
    return await Document.getInitialProps(ctx);
  };

  override render(): JSX.Element {
    const currentLocale = this.props.__NEXT_DATA__.locale ?? i18nConfig.i18n.defaultLocale;

    return (
      <Html lang={currentLocale}>
        <Head>
          <meta charSet="utf-8" />
          <meta name="robots" content="index,follow,max-image-preview:large" />

          {this.props.styleTags}
          {/* Preload the fonts */}
          <link rel="preconnect" href="https://fonts.googleapis.com" />
          <link
            href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap"
            rel="stylesheet"
          />
          <link
            href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700;1,900&display=swap"
            rel="stylesheet"
          />
          <link rel="preload" href="/fonts/AvenirNext-Regular.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-Bold.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-BoldItalic.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-DemiBold.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-DemiBoldItalic.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-Medium.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-MediumItalic.woff2" as="font" crossOrigin="anonymous" />
          <link href="/icons/favicon.ico" rel="shortcut icon" />

          {/* TODO add manifest */}
          {/*<link href="/static/site.webmanifest" rel="manifest" />*/}
          <meta name="theme-color" content="#ffffff" />
          <meta name="msapplication-TileColor" content="#ffffff" />
          <link href="/icons/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180" />
          <link href="/icons/favicon-32x32.png" rel="icon" sizes="32x32" type="image/png" />
          <link href="/icons/favicon-16x16.png" rel="icon" sizes="16x16" type="image/png" />
          {/* Safari Pinned Tab Icon: https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/pinnedTabs/pinnedTabs.html */}
          <link color="#4a9885" href="/icons/favicon.svg" rel="mask-icon" />
          <script
            defer
            data-domain={siteDomain}
            data-api="https://blue-bar-dsebastien-19fd.developassion.workers.dev/api/v1/event"
            src="https://blue-bar-dsebastien-19fd.developassion.workers.dev/content/script.js"
          ></script>
          <meta content="/browserconfig.xml" name="msapplication-config" />
          <Script id="site-microdata-script" type="application/ld+json">
            {JSON.stringify(siteMicrodata)}
          </Script>
        </Head>
        <body className="loading">
          <ColorModeScript initialColorMode={customTheme.config.initialColorMode} />
          <Main />
          <NextScript />
        </body>
      </Html>
    );
  }
}
