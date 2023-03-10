import { AppProps } from 'next/app';
import Head from 'next/head';

import '../styles/tailwind.scss';
import '../styles/tailwind-utilities.scss';
import '../styles/fonts.scss';
import '../styles/global.scss';
import { ThemeProvider } from 'next-themes';
import React, { useEffect, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { SITE_TITLE } from '../lib/constants';
import useRouterScroll from '../lib/client/utils/use-router-scroll';
import { appWithTranslation, SSRConfig } from 'next-i18next';
import { GetServerSideProps } from 'next';
import { NextApiRequestCookies } from 'next/dist/server/api-utils';
import { createBrowserSupabaseClient, Session } from '@supabase/auth-helpers-nextjs';
import { useRouter } from 'next/router';
import { ChakraBaseProvider, cookieStorageManagerSSR, createLocalStorageManager } from '@chakra-ui/react';
import ProgressBar from '../components/layout/ProgressBar';
import { customTheme, defaultToastOptions } from '../chakra-ui.config';
import { SessionContextProvider } from '@supabase/auth-helpers-react';

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
  }, [supabaseClient.auth, router.asPath]);

  useRouterScroll({
    behavior: 'smooth',
    idOfElementToScroll: '__next',
  });

  return (
    <>
      <Head>
        {/* Why here? https://nextjs.org/docs/messages/no-document-viewport-meta */}
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>{SITE_TITLE}</title>
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
    </>
  );
};

export default appWithTranslation(App);
