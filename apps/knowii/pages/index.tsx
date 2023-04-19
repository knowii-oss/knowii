//import styles from './index.module.scss';
import { Hero } from '../components/home/hero';
import { Layout } from '../components/layout/layout';
import { i18nConfig } from '../../../i18n.config.mjs';
import { GetStaticProps } from 'next';
import { CustomPageProps } from './_app';

export const getStaticProps: GetStaticProps<Partial<CustomPageProps>> = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../libs/common/src/lib/messages/${locale}.json`)).default;

  return {
    props: {
      messages,
      // Note that when `now` is passed to the app, you need to make sure the
      // value is updated from time to time, so relative times are updated. See
      // https://next-intl-docs.vercel.app/docs/usage/configuration#global-now-value
      now: new Date().getTime(),
    },
  };
};

// eslint-disable-next-line
interface HomeProps {}

const Home = (_props: HomeProps) => {
  return (
    <Layout>
      <Hero />
      {/*<Features />*/}
      {/*<Pricing />*/}
    </Layout>
  );
};

export default Home;
