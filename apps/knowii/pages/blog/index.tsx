import { Box, Container, VStack } from '@chakra-ui/react';
import { GetStaticProps } from 'next';
import { useTranslations } from 'next-intl';
import { getAllMdxEntries, MdxEntry } from '@knowii/server';
import { WebsiteDataType, LocaleCode } from '@knowii/common';
import Layout from '../../components/layout/layout';
import { BlogPostOverview, PageHeader } from '@knowii/client-ui';
import { i18nConfig } from '../../../../i18n.config.mjs';
import { CustomPageProps } from '../_app';

interface BlogPageProps {
  entries: MdxEntry[];
}

export const getStaticProps: GetStaticProps<Partial<CustomPageProps> & BlogPageProps> = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;

  const entries = (
    await getAllMdxEntries({
      type: WebsiteDataType.BLOG,
      locales: i18nConfig.i18n.locales as LocaleCode[],
    })
  )
    /* Only show posts that have been set to published */
    .filter((entry) => entry.frontMatter?.published)
    /* Sort entries by date descending */
    .sort((a, b) => {
      return b.frontMatter.publishedOn > a.frontMatter.publishedOn ? 1 : -1;
    });

  const messages = (await import(`../../../../libs/common/src/lib/messages/${locale}.json`)).default;

  const retVal = {
    props: {
      messages,
      entries,
      // Note that when `now` is passed to the app, you need to make sure the
      // value is updated from time to time, so relative times are updated. See
      // https://next-intl-docs.vercel.app/docs/usage/configuration#global-now-value
      now: new Date().getTime(),
    },
  };

  return retVal;
};

export function BlogPage({ entries }: BlogPageProps) {
  const t = useTranslations('blogPage');

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
            {entries?.map(({ slug, frontMatter: { title, summary, image, publishedOn } }) => (
              <BlogPostOverview key={slug} slug={slug} title={title} summary={summary} image={image} publishedOn={publishedOn} />
            ))}
          </VStack>
        </Container>
      </Box>
    </Layout>
  );
}

export default BlogPage;
