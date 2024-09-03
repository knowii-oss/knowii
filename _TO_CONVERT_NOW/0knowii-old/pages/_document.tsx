import React, { ReactElement } from 'react';
import Document, { Html, Head, Main, NextScript, DocumentContext, DocumentInitialProps } from 'next/document';
import { ColorModeScript } from '@chakra-ui/react';
import { customTheme } from '../chakra-ui.config';
import { IS_BROWSER, SITE_AUTHOR_MICRODATA } from '@knowii/common';
import Script from 'next/script';
import { i18nConfig } from '../../../i18n.config.mjs';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteDescription = require('../metadata.json').description;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteDomain = require('../metadata.json').domain;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteName = require('../metadata.json').siteName;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteTitle = require('../metadata.json').title;

const siteMicrodata = {
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  name: siteName,
  alternateName: siteTitle,
  description: siteDescription,
  publisher: SITE_AUTHOR_MICRODATA,
  url: IS_BROWSER ? window.location.origin : '',
};


          {/* TODO add manifest */}
          {/*<link href="/static/site.webmanifest" rel="manifest" />*/}

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

