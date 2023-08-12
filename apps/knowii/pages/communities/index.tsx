import { Box, Container, Heading, VStack } from '@chakra-ui/react';
import { GetStaticProps } from 'next';
import { useTranslations } from 'next-intl';
import Layout from '../../components/layout/layout';
import { PageHeader } from '@knowii/client-ui';
import { i18nConfig } from '../../../../i18n.config.mjs';
import { CustomPageProps } from '../_app';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface CommunitiesPageProps {}

export const getStaticProps: GetStaticProps<Partial<CustomPageProps> & CommunitiesPageProps> = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;

  const messages = (await import(`../../../../libs/common/src/lib/messages/${locale}.json`)).default;

  const retVal = {
    props: {
      messages,
      // Note that when `now` is passed to the app, you need to make sure the
      // value is updated from time to time, so relative times are updated. See
      // https://next-intl-docs.vercel.app/docs/usage/configuration#global-now-value
      now: new Date().getTime(),
    },
  };

  return retVal;
};

export function CommunitiesPage(_props: CommunitiesPageProps) {
  const t = useTranslations('communitiesPage');

  return (
    <Layout
      customMeta={{
        title: t('title'),
      }}
    >
      <PageHeader title={t('title')} description={t('description')} />
      <Box px={4} py={12}>
        <Container maxW="5xl">
          <VStack spacing={4} align="stretch">
            <Heading>{t('comingSoon')}</Heading>
          </VStack>
        </Container>
      </Box>
    </Layout>
  );
}

export default CommunitiesPage;
