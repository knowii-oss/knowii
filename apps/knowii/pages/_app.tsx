import { AppProps } from 'next/app';
import Head from 'next/head';

import '../styles/tailwind.scss';
import '../styles/tailwind-utilities.scss';
import '../styles/fonts.scss';
import '../styles/global.scss';
import { ThemeProvider } from 'next-themes';
import React from 'react';
import ThemeSwitcher from '../components/theme-switcher';
import useRouterScroll from '../lib/client/utils/use-router-scroll';

/**
 * Wrapper around all pages
 * @param Component the wrapped component
 * @param pageProps
 * @constructor
 */
const App = ({ Component, pageProps }: AppProps) => {
  useRouterScroll({
    behavior: 'smooth',
    idOfElementToScroll: '__next',
  });

  return (
    <>
      <Head>
        <title>Knowii</title>
      </Head>
      <main className="app">
        {/* Use the ThemeProvider of next-themes, combined with Tailwind: https://github.com/pacocoursey/next-themes#with-tailwind */}
        <ThemeProvider attribute="class">
          <ThemeSwitcher />
          <Component {...pageProps} />
        </ThemeProvider>
      </main>
    </>
  );
};

export default App;
