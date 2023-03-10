//import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { GetServerSideProps } from 'next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';

//import styles from './index.module.scss';
import Hero from '../components/home/Hero';
import Layout from '../components/layout/Layout';
import Features from '../components/home/Features';
import Pricing from '../components/home/Pricing';

import { i18nConfig } from '../next-i18next.config.mjs';

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  //const supabase = createServerSupabaseClient(ctx);

  const translations = await serverSideTranslations(ctx.locale!, ['common', 'home'], i18nConfig);

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
