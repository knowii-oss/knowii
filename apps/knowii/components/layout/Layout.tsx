import { Box } from '@chakra-ui/react';
import Head from 'next/head';
import { PropsWithChildren, useMemo } from 'react';
import NavBar from './NavBar';
import Footer from './Footer';
import { SITE_DESCRIPTION, SITE_TITLE } from '../../lib/constants';

export default function Layout({
  pageTitle,
  children,
}: PropsWithChildren<{
  pageTitle?: string;
}>) {
  // FIXME validate this works
  const title = useMemo(() => `${pageTitle ? `${String(pageTitle)} - ` : SITE_TITLE}`, [pageTitle]);

  return (
    <>
      <Head>
        <title>{title}</title>
        <meta name="description" content={SITE_DESCRIPTION} />
      </Head>
      <NavBar />
      <Box minH="100vh" pt={20}>
        {children}
      </Box>
      <Footer />
    </>
  );
}
