import React from 'react';
import Link from 'next/link';
import { GetStaticProps } from 'next';
import { useTranslations } from 'next-intl';
import { Layout } from '../components/layout/layout';
import { HOME_URL } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface InternalServerErrorPageProps {}

export function InternalServerErrorPage(_props: InternalServerErrorPageProps) {
  const t = useTranslations('internalServerErrorPage');

  return (
    <Layout
      customMeta={{
        title: t('title'),
      }}
    >
      <div className="flex flex-col text-center px-8 py-36 bg-primary-500 dark:bg-steel-500 gap-2">
        <h1 className="text-white">{t('title')}</h1>
        <h2 className="text-white">{t('message')}</h2>
        <Link href={HOME_URL} className="site-button">
          {t('goBackHomeLink')}
        </Link>
      </div>
    </Layout>
  );
}

export default InternalServerErrorPage;
