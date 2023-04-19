import { Box, Container } from '@chakra-ui/react';
import { GetStaticProps } from 'next';
import { useUser } from '@supabase/auth-helpers-react';
import { useTranslations } from 'next-intl';

//import styles from './index.module.scss';
import { Layout } from '../../components/layout/layout';
import { Loader, PageHeader } from '@knowii/client-ui';
import { i18nConfig } from '../../../../i18n.config.mjs';
import { CustomPageProps } from '../_app';

export const getStaticProps: GetStaticProps<Partial<CustomPageProps>> = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../../libs/common/src/lib/messages/${locale}.json`)).default;

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

/**
 * Dashboard page
 * @constructor
 */
export function DashboardPage() {
  const t = useTranslations('dashboardPage');
  const user = useUser();

  return (
    <Layout customMeta={{ title: t('pageTitle') }}>
      <PageHeader title={t('pageTitle')} description={t('pageDescription')} align="start" />
      <Box px={4} py={12}>
        <Container maxW="5xl">{!user ? <Loader /> : <div>Coming soon</div>}</Container>
      </Box>
    </Layout>
  );
}

export default DashboardPage;
