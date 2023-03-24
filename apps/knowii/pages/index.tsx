//import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { GetServerSideProps } from 'next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';

//import styles from './index.module.scss';
import { Hero } from '../components/home/hero';
import { Features } from '../components/home/features';
import { Layout } from '../components/layout/layout';
import { Pricing } from '../components/home/pricing';

import { i18nConfig } from '../../../next-i18next.config.mjs';

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  //const supabase = createServerSupabaseClient(ctx);

  const translations = await serverSideTranslations(ctx.locale ? ctx.locale : 'en', ['common', 'home'], i18nConfig);

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
