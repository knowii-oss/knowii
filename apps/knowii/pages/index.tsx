//import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { GetServerSideProps } from 'next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';

//import styles from './index.module.scss';
import { Hero } from '../components/home/hero';
import { Features } from '../components/home/features';
import { Layout } from '../components/layout/layout';
import { Pricing } from '../components/home/pricing';

import { i18nConfig } from '../../../next-i18next.config.mjs';
import { I18N_TRANSLATIONS_COMMON, I18N_TRANSLATIONS_HOME } from '@knowii/common';

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  //const supabase = createServerSupabaseClient(ctx);

  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const translations = await serverSideTranslations(locale, [I18N_TRANSLATIONS_COMMON, I18N_TRANSLATIONS_HOME], i18nConfig);

  return {
    props: { ...translations },
  };
};

const Home = () => {
  return (
    <Layout>
      <Hero />
      <Features />
      <Pricing />
    </Layout>
  );
};

export default Home;
