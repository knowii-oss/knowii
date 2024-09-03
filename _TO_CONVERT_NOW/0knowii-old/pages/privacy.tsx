import React from 'react';
import { GetStaticProps } from 'next';
import { useTranslations } from 'next-intl';
import { Layout } from '../components/layout/layout';
import { i18nConfig } from '../../../i18n.config.mjs';
import { CustomPageProps } from './_app';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface PrivacyPolicyPageProps {}

export const getStaticProps: GetStaticProps<Partial<CustomPageProps> & PrivacyPolicyPageProps> = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../libs/common/src/lib/messages/${locale}.json`)).default;

  return {
    props: { messages },
  };
};

export function PrivacyPolicyPage(_props: PrivacyPolicyPageProps) {
  const t = useTranslations('privacyPolicyPage');

  return (
    <Layout
      customMeta={{
        title: t('title'),
      }}
    >
      <div className="flex flex-col text-center px-8 py-12 bg-primary-500 dark:bg-steel-500">
        <h1 className="text-white">{t('title')}</h1>
      </div>
      <div className="flex flex-col px-96 py-6">
        <p>Coming soon...</p>
        <br />
      </div>
    </Layout>
  );
}

export default PrivacyPolicyPage;
