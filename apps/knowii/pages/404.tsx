import React from 'react';
import Link from 'next/link';
import { Layout } from '../components/layout/layout';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface NotFoundPageProps {}

export function NotFoundPage(_props: NotFoundPageProps) {
  return (
    <Layout
      customMeta={{
        title: '404 Page not found',
      }}
    >
      <div className="max-w-2xl mx-auto">
        <h1 className="page-heading">Doh...</h1>
        <p className="text-gray-900 dark:text-gray-400 mb-8">You seem to be lost...</p>
        <Link href="/" className="site-button">
          Go back home
        </Link>
      </div>
    </Layout>
  );
}

export default NotFoundPage;
