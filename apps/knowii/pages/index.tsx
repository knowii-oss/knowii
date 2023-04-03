//import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';

//import styles from './index.module.scss';
import { Hero } from '../components/home/hero';
import { Features } from '../components/home/features';
import { Layout } from '../components/layout/layout';
import { i18nConfig } from '../../../i18n.config.mjs';
import { GetStaticProps } from 'next';
import { Pricing } from '../components/home/pricing';

export const getStaticProps: GetStaticProps = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../libs/common/src/lib/messages/${locale}.json`)).default;

  return {
    props: { messages },
  };
};

// eslint-disable-next-line
interface HomeProps {}

const Home = (_props: HomeProps) => {
  return (
    <Layout>
      <Hero />
      <Features />
      <Pricing />
    </Layout>
  );
};

export default Home;
