import { AppProps } from 'next/app';
import { ChakraBaseProvider, cookieStorageManagerSSR, localStorageManager } from '@chakra-ui/react';

import '../styles/tailwind.scss';
import '../styles/tailwind-utilities.scss';
import '../styles/fonts.scss';
import '../styles/global.scss';

import 'prismjs/themes/prism-tomorrow.min.css';
import 'prismjs/plugins/line-numbers/prism-line-numbers.css';

import { ThemeProvider } from 'next-themes';
import React, { useEffect, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useRouterScroll } from '@knowii/client';
import { GetServerSideProps } from 'next';
import { NextApiRequestCookies } from 'next/dist/server/api-utils';
import { createBrowserSupabaseClient, Session } from '@supabase/auth-helpers-nextjs';
import { useRouter } from 'next/router';
import ProgressBar from '../components/layout/progress-bar';
import { customTheme, defaultToastOptions } from '../chakra-ui.config';
import { SessionContextProvider } from '@supabase/auth-helpers-react';
import Head from 'next/head';
import { NextIntlProvider } from 'next-intl';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteTitle = require('../../../libs/common/src/lib/metadata.json').title;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteDescription = require('../../../libs/common/src/lib/metadata.json').description;

const queryClient = new QueryClient();

export const getServerSideProps: GetServerSideProps = async ({ req }) => {
  const retVal: { props: Partial<CustomPageProps> } = {
    props: {
      cookies: req.cookies,
    },
  };
  return retVal;
};

interface CustomPageProps {
  cookies?: NextApiRequestCookies;
  initialSession?: Session | null;
  messages: Messages;
  now: number;
}

/**
 * Wrapper around all pages
 * @param Component the wrapped component
 * @param pageProps
 * @constructor
 */
const App = ({ Component, pageProps }: AppProps<CustomPageProps>) => {
  const router = useRouter();
  const [supabaseClient] = useState(() => createBrowserSupabaseClient());
  const { cookies } = pageProps;
  const colorModeManager = typeof cookies === 'string' ? cookieStorageManagerSSR(cookies) : localStorageManager;

  // redirect to signin page if user is signed out while being on a protected page
  useEffect(() => {
    const {
      data: { subscription },
    } = supabaseClient.auth.onAuthStateChange((event) => {
      if (event === 'SIGNED_OUT' && (router.asPath.startsWith('/app') || router.asPath.startsWith('/account')))
        router.replace('/auth/signin');
    });

    return () => subscription.unsubscribe();
  }, [supabaseClient.auth, router]);

  useRouterScroll({
    behavior: 'smooth',
    idOfElementToScroll: '__next',
  });

  return (
    <>
      <NextIntlProvider
        // Reference: https://next-intl-docs.vercel.app/docs/usage/configuration

        // Messages can be received from individual pages or configured
        // globally in this module (`App.getInitialProps`). Note that in
        // the latter case the messages are available as a top-level prop
        // and not nested within `pageProps`.
        messages={pageProps.messages}
        // To achieve consistent date, time and number formatting
        // across the app, you can define a set of global formats.
        formats={{
          dateTime: {
            short: {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
            },
          },
        }}
        // Providing an explicit value for `now` ensures consistent formatting of
        // relative values regardless of the server or client environment.
        now={new Date(pageProps.now)}
        // Also an explicit time zone is helpful to ensure dates render the
        // same way on the client as on the server, which might be located
        // in a different time zone.
        timeZone="Europe/Brussels"
      >
        <ChakraBaseProvider
          theme={customTheme}
          colorModeManager={colorModeManager}
          toastOptions={{
            defaultOptions: defaultToastOptions,
          }}
        >
          <Head>
            {/* Why here? https://nextjs.org/docs/messages/no-document-viewport-meta */}
            <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />
            {/* Why here? https://nextjs.org/docs/messages/no-document-title */}
            <title>{siteTitle}</title>
            <meta name="description" content={siteDescription} />
          </Head>
          <main className="app">
            <ProgressBar />

            <QueryClientProvider client={queryClient}>
              <SessionContextProvider supabaseClient={supabaseClient} initialSession={pageProps.initialSession}>
                {/* Use the ThemeProvider of next-themes, combined with Tailwind: https://github.com/pacocoursey/next-themes#with-tailwind */}
                <ThemeProvider attribute="class">
                  <Component {...pageProps} />
                </ThemeProvider>
              </SessionContextProvider>
            </QueryClientProvider>
          </main>
        </ChakraBaseProvider>
      </NextIntlProvider>
    </>
  );
};

export default App;
