import { Box, Container, VStack } from '@chakra-ui/react';
import { GetServerSideProps } from 'next';
import ErrorPage from 'next/error';
import { useRouter } from 'next/router';
import { Loader, PageHeader } from '@knowii/client-ui';
import { Layout } from '../../components/layout/layout';
import { COMMUNITIES_BASE_URL } from '@knowii/common';
import { CustomPageProps } from '../_app';
import { i18nConfig } from '../../../../i18n.config.mjs';
import { useTranslations } from 'next-intl';

interface CommunityPageProps {
  slug: string;
}

export const getServerSideProps: GetServerSideProps<Partial<CustomPageProps> & CommunityPageProps> = async (ctx) => {
  const slug = ctx.params?.slug as string;

  if (!slug) {
    console.warn('No slug provided. Redirecting to the communities home page');
    return {
      redirect: {
        destination: COMMUNITIES_BASE_URL,
        permanent: false,
      },
    };
  }

  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../../libs/common/src/lib/messages/${locale}.json`)).default;

  // FIXME fetch the community

  return {
    props: {
      messages,
      // Note that when `now` is passed to the app, you need to make sure the
      // value is updated from time to time, so relative times are updated. See
      // https://next-intl-docs.vercel.app/docs/usage/configuration#global-now-value
      now: new Date().getTime(),
      slug,
      // FIXME add community data
    },
  };
};

/**
 * Renders a specific blog post
 * @constructor
 */
export function CommunityPage(props: CommunityPageProps) {
  const router = useRouter();

  if (router.isFallback) {
    return <Loader />;
  } else if (!props.slug) {
    // FIXME replace with check for community payload
    return <ErrorPage statusCode={404} />;
  }

  // eslint-disable-next-line react-hooks/rules-of-hooks
  const t = useTranslations('communityPage');

  // WARNING: The message parameter name MUST correspond in the translation file
  const pageTitle = `${t('title', { communityName: 'Foo' })}`; // FIXME add community name to the page title

  return (
    <>
      <Layout
        customMeta={{
          title: pageTitle,
          description: '', // FIXME use community description
          //image: ..., // TODO add community image
          // type: ..., // TODO set type
          // keywords: ....tags.join(', '), // TODO add tags as page keywords
        }}
      >
        <PageHeader title="Community names goes here" align="center" containerMaxWidth="3xl">
          <VStack spacing={6} mt={6}>
            Hello world
          </VStack>
        </PageHeader>
        <Box px={4} py={12}>
          <Container maxW="3xl" w="full">
            Community data goes here
          </Container>
        </Box>
      </Layout>
    </>
  );
}

export default CommunityPage;
