import { Box, Container } from '@chakra-ui/react';
import { GetServerSideProps } from 'next';
import { useRouter } from 'next/router';
import { Loader, PageHeader } from '@knowii/client-ui';
import { Layout } from '../../components/layout/layout';
import { COMMUNITIES_BASE_URL } from '@knowii/common';
import { CustomPageProps } from '../_app';
import { i18nConfig } from '../../../../i18n.config.mjs';
import { useTranslations } from 'next-intl';
import { dehydrate, QueryClient, useQuery } from '@tanstack/react-query';
import { getCommunity } from '@knowii/client';

interface CommunityPageProps {
  slug: string;
}

export const getServerSideProps: GetServerSideProps<Partial<CustomPageProps> & CommunityPageProps> = async (ctx) => {
  const slug = ctx.params?.slug as string;

  if (!slug) {
    // No slug provided. Redirecting to the communities home page
    return {
      redirect: {
        destination: COMMUNITIES_BASE_URL,
        permanent: false,
      },
    };
  }

  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../../libs/common/src/lib/messages/${locale}.json`)).default;

  const queryClient = new QueryClient();

  try {
    await queryClient.fetchQuery(['community', slug], () => getCommunity(slug, ctx.req.headers.host));
  } catch (err) {
    console.log('Failed to load the community. Redirecting the user: ', err);
    return {
      redirect: {
        destination: COMMUNITIES_BASE_URL,
        permanent: false,
      },
    };
  }

  return {
    props: {
      messages,
      // Note that when `now` is passed to the app, you need to make sure the
      // value is updated from time to time, so relative times are updated. See
      // https://next-intl-docs.vercel.app/docs/usage/configuration#global-now-value
      now: new Date().getTime(),
      slug,
      dehydratedState: dehydrate(queryClient),
    },
  };
};

const useCommunity = (slug: string) => {
  return useQuery(['community', slug], () => getCommunity(slug));
};

/**
 * Renders a specific blog post
 * @constructor
 */
export function CommunityPage(props: CommunityPageProps) {
  const router = useRouter();

  // TODO Extract and use isLoading, isFetching, etc
  const { data, isLoading, isFetching } = useCommunity(props.slug);

  if (router.isFallback) {
    return <Loader />;
  }

  // eslint-disable-next-line react-hooks/rules-of-hooks
  const t = useTranslations('communityPage');

  // WARNING: The message parameter name MUST correspond in the translation file
  const pageTitle = `${t('title', { communityName: data?.name })}`;

  return (
    <>
      <Layout
        customMeta={{
          title: pageTitle,
          description: data?.description,
          //image: ..., // TODO add community image
          // type: ..., // TODO set type
          // keywords: ....tags.join(', '), // TODO add tags as page keywords
        }}
      >
        <PageHeader
          title={`${t('title', { communityName: data?.name })}`}
          align="center"
          containerMaxWidth="3xl"
          description={data?.description}
        >
          {isLoading || isFetching ? <Loader /> : null}
        </PageHeader>
        <Box px={4} py={12} className="grid md-grid-flow-row md:grid-cols-2 gap-8">
          <Container maxW="3xl" className="">
            <div className="rounded-t-lg bg-pink-500 p-2 text-center font-bold text-xl">{t('news')}</div>
            <div className="bg-gray-700 p-4 rounded-b-lg">{t('comingSoon')}</div>
          </Container>
          <Container maxW="3xl" className="">
            <div className="rounded-t-lg bg-pink-500 p-2 text-center font-bold text-xl">{t('recentResources')}</div>
            <div className="bg-gray-700 p-4 rounded-b-lg">{t('comingSoon')}</div>
          </Container>
          <Container maxW="3xl" className="">
            <div className="rounded-t-lg bg-pink-500 p-2 text-center font-bold text-xl">{t('resourceCollections')}</div>
            <div className="bg-gray-700 p-4 rounded-b-lg">{t('comingSoon')}</div>
          </Container>
          <Container maxW="3xl" className="">
            <div className="rounded-t-lg bg-pink-500 p-2 text-center font-bold text-xl">{t('peopleDirectory')}</div>
            <div className="bg-gray-700 p-4 rounded-b-lg">{t('comingSoon')}</div>
          </Container>
        </Box>
      </Layout>
    </>
  );
}

export default CommunityPage;
