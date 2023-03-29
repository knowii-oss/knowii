import { AppProps } from 'next/app';
import { ChakraProvider } from '@chakra-ui/react';

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
import { appWithTranslation, SSRConfig } from 'next-i18next';
import { GetServerSideProps } from 'next';
import { NextApiRequestCookies } from 'next/dist/server/api-utils';
import { createBrowserSupabaseClient, Session } from '@supabase/auth-helpers-nextjs';
import { useRouter } from 'next/router';
import { ChakraBaseProvider, cookieStorageManagerSSR, createLocalStorageManager } from '@chakra-ui/react';
import ProgressBar from '../components/layout/progress-bar';
import { customTheme, defaultToastOptions } from '../chakra-ui.config';
import { SessionContextProvider } from '@supabase/auth-helpers-react';
import Head from 'next/head';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteTitle = require('../../../libs/common/src/lib/metadata.json').title;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteDescription = require('../../../libs/common/src/lib/metadata.json').description;

const queryClient = new QueryClient();

export const getServerSideProps: GetServerSideProps = async ({ req }) => {
  return {
    props: {
      cookies: req.cookies,
    },
  };
};

/**
 * Wrapper around all pages
 * @param Component the wrapped component
 * @param pageProps
 * @constructor
 */
const App = ({ Component, pageProps }: AppProps<{ cookies?: NextApiRequestCookies; initialSession?: Session | null } & SSRConfig>) => {
  const router = useRouter();
  const [supabaseClient] = useState(() => createBrowserSupabaseClient());
  const { cookies } = pageProps;
  const colorModeManager = typeof cookies === 'string' ? cookieStorageManagerSSR(cookies) : createLocalStorageManager('color-mode');

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
      <ChakraProvider>
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
            <ChakraBaseProvider
              theme={customTheme}
              colorModeManager={colorModeManager}
              toastOptions={{
                defaultOptions: defaultToastOptions,
              }}
            >
              <SessionContextProvider supabaseClient={supabaseClient} initialSession={pageProps.initialSession}>
                {/* Use the ThemeProvider of next-themes, combined with Tailwind: https://github.com/pacocoursey/next-themes#with-tailwind */}
                <ThemeProvider attribute="class">
                  <Component {...pageProps} />
                </ThemeProvider>
              </SessionContextProvider>
            </ChakraBaseProvider>
          </QueryClientProvider>
        </main>
      </ChakraProvider>
    </>
  );
};

export default appWithTranslation(App);
