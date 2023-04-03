import React from 'react';
import Link from 'next/link';
import { GetStaticProps } from 'next';
import { useTranslations } from 'next-intl';
import { Layout } from '../components/layout/layout';
import { i18nConfig } from '../../../i18n.config.mjs';

export const getStaticProps: GetStaticProps = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../libs/common/src/lib/messages/${locale}.json`)).default;

  return {
    props: { messages },
  };
};

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface NotFoundPageProps {}

export function NotFoundPage(_props: NotFoundPageProps) {
  const t = useTranslations('pageNotFoundPage');

  return (
    <Layout
      customMeta={{
        title: t('title'),
      }}
    >
      <div className="flex flex-col text-center px-8 py-36 bg-primary-500 dark:bg-steel-500 gap-2">
        <h1 className="text-white">{t('title')}</h1>
        <h2 className="text-white">{t('message')}</h2>
        <Link href="/" className="site-button">
          {t('goBackHomeLink')}
        </Link>
      </div>
    </Layout>
  );
}

export default NotFoundPage;
