import React, { ReactElement } from 'react';
import Document, { Html, Head, Main, NextScript, DocumentContext, DocumentInitialProps } from 'next/document';
import { ColorModeScript } from '@chakra-ui/react';
import { customTheme } from '../chakra-ui.config';

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
    return (
      <Html lang="en">
        <Head>
          {this.props.styleTags}
          {/* Preload the fonts */}
          <link rel="preload" href="/fonts/AvenirNext-Regular.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-Bold.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-BoldItalic.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-DemiBold.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-DemiBoldItalic.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-Medium.woff2" as="font" crossOrigin="anonymous" />
          <link rel="preload" href="/fonts/AvenirNext-MediumItalic.woff2" as="font" crossOrigin="anonymous" />
          <link href="/static/icons/favicon.ico" rel="shortcut icon" />
          {/* TODO add manifest */}
          {/*<link href="/static/site.webmanifest" rel="manifest" />*/}
          <meta content="#ffffff" name="theme-color" />
          <meta content="#ffffff" name="msapplication-TileColor" />
          <link href="/static/icons/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180" />
          <link href="/static/icons/favicon-32x32.png" rel="icon" sizes="32x32" type="image/png" />
          <link href="/static/icons/favicon-16x16.png" rel="icon" sizes="16x16" type="image/png" />
          {/* Safari Pinned Tab Icon: https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/pinnedTabs/pinnedTabs.html */}
          <link color="#4a9885" href="/static/icons/favicon.svg" rel="mask-icon" />

          <meta content="/static/browserconfig.xml" name="msapplication-config" />
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
