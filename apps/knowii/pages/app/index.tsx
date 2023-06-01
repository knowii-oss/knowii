import { Box, Container } from '@chakra-ui/react';
import { GetServerSideProps } from 'next';
import { useUser } from '@supabase/auth-helpers-react';
import { useTranslations } from 'next-intl';

//import styles from './index.module.scss';
import { Layout } from '../../components/layout/layout';
import { Loader, PageHeader } from '@knowii/client-ui';
import { i18nConfig } from '../../../../i18n.config.mjs';
import { CustomPageProps } from '../_app';
import { Database } from '@knowii/common';
import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface DashboardPageProps {}

export const getServerSideProps: GetServerSideProps<Partial<CustomPageProps> & DashboardPageProps> = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../../libs/common/src/lib/messages/${locale}.json`)).default;

  // Refreshing the session to make sure it includes what we need
  // WARNING: This is mandatory because of the customizations we make to app_meta_data and user_meta_data
  // which are not immediately reflected in the JWT token generated after user sign up
  // We normally only need this once after signup, but we accept the tradeoff
  const supabaseClient = createServerSupabaseClient<Database>(ctx);
  await supabaseClient.auth.refreshSession();

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
export function DashboardPage(_props: DashboardPageProps) {
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
