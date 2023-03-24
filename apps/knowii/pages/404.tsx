import React from 'react';
import Link from 'next/link';
import { Layout } from '../components/layout/layout';
// import { I18N_TRANSLATIONS_404 } from '@knowii/common';
// import { i18nConfig } from '../../../next-i18next.config.mjs';
// import { GetStaticProps } from 'next';
// import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
//import { useTranslation } from 'next-i18next';

// export const getStaticProps: GetStaticProps = async (ctx) => {
//   const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
//   const translations = await serverSideTranslations(locale, [I18N_TRANSLATIONS_404], i18nConfig);
//   return {
//     props: {
//       ...translations,
//     },
//   };
// };

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface NotFoundPageProps {}

export function NotFoundPage(_props: NotFoundPageProps) {
  //const { t } = useTranslation(I18N_TRANSLATIONS_404);

  return (
    <Layout
      customMeta={{
        title: 'Doh...', //t('title'),
      }}
    >
      <div className="flex flex-col text-center px-8 py-36 bg-primary-500 dark:bg-steel-500 gap-2">
        <h1 className="text-white">Doh...{/*t('title')*/}</h1>
        <h2 className="text-white">You seem to be lost...{/*t('message')*/}</h2>
        <Link href="/" className="site-button">
          👉 Go back home
          {/*t('goBackHomeLink')*/}
        </Link>
      </div>
    </Layout>
  );
}

export default NotFoundPage;
