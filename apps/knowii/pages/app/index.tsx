import { Box, Container } from '@chakra-ui/react';
import { GetStaticProps } from 'next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';

//import styles from './index.module.scss';
import { i18nConfig } from '../../../../next-i18next.config.mjs';
import { Layout } from '../../components/layout/layout';
import { ClientsList, Loader, PageHeader } from '@knowii/client-ui';
import { useTranslation } from 'next-i18next';
import { useUser } from '@supabase/auth-helpers-react';

export const getStaticProps: GetStaticProps = async (ctx) => {
  const translations = await serverSideTranslations(ctx.locale ? ctx.locale : 'en', ['common', 'app'], i18nConfig);
  return {
    props: {
      ...translations,
    },
  };
};

/**
 * Dashboard page
 * @constructor
 */
export function DashboardPage() {
  const { t } = useTranslation('app');
  const user = useUser();

  return (
    <Layout customMeta={{ title: t('dashboard.pageTitle') }}>
      <PageHeader title={t('dashboard.pageTitle')} description={t('dashboard.pageDescription')} align="start" />
      <Box px={4} py={12}>
        <Container maxW="5xl">{!user ? <Loader /> : <ClientsList />}</Container>
      </Box>
    </Layout>
  );
}

export default DashboardPage;
