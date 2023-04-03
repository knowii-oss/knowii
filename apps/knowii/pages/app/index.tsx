import { Box, Container } from '@chakra-ui/react';
import { GetStaticProps } from 'next';
import { useUser } from '@supabase/auth-helpers-react';
import { useTranslations } from 'next-intl';

//import styles from './index.module.scss';
import { Layout } from '../../components/layout/layout';
import { ClientsList, Loader, PageHeader } from '@knowii/client-ui';
import { i18nConfig } from '../../../../i18n.config.mjs';

export const getStaticProps: GetStaticProps = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../../libs/common/src/lib/messages/${locale}.json`)).default;

  return {
    props: { messages },
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
        <Container maxW="5xl">{!user ? <Loader /> : <ClientsList />}</Container>
      </Box>
    </Layout>
  );
}

export default DashboardPage;
