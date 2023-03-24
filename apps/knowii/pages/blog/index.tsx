import { Box, Container, VStack } from '@chakra-ui/react';
import { GetStaticProps } from 'next';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { getAllMdxEntries, MdxEntry } from '@knowii/server';
import { I18N_TRANSLATIONS_BLOG, I18N_TRANSLATIONS_COMMON, WebsiteDataType } from '@knowii/common';
import Layout from '../../components/layout/layout';
import { BlogPost, PageHeader } from '@knowii/client-ui';
import { i18nConfig } from '../../../../next-i18next.config.mjs';

export const getStaticProps: GetStaticProps = async (ctx) => {
  const entries = (
    await getAllMdxEntries({
      type: WebsiteDataType.BLOG,
      currentLocale: ctx.locale,
      defaultLocale: i18nConfig.i18n.defaultLocale,
      locales: i18nConfig.i18n.locales,
    })
  )
    /* Only show posts that have been set to published */
    .filter((entry) => entry.frontMatter?.published)
    /* Sort entries by date descending */
    .sort((a, b) => {
      return b.frontMatter.publishedOn > a.frontMatter.publishedOn ? 1 : -1;
    });

  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const translations = await serverSideTranslations(locale, [I18N_TRANSLATIONS_COMMON, I18N_TRANSLATIONS_BLOG], i18nConfig);

  return {
    props: {
      ...translations,
      entries,
    },
  };
};

interface BlogPageProps {
  entries: Array<MdxEntry>;
}

export function BlogPage({ entries }: BlogPageProps) {
  const { t } = useTranslation(I18N_TRANSLATIONS_BLOG);

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
              <BlogPost key={slug} slug={slug} title={title} summary={summary} image={image} date={publishedOn} />
            ))}
          </VStack>
        </Container>
      </Box>
    </Layout>
  );
}

export default BlogPage;
