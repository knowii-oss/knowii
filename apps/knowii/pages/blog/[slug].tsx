import { Box, Container, VStack } from '@chakra-ui/react';
import { GetStaticPaths, GetStaticProps } from 'next';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { MDXRemote, MDXRemoteSerializeResult } from 'next-mdx-remote';
import Head from 'next/head';
import Image from 'next/image';
import { useMemo } from 'react';
import { BlogPostMeta, PageHeader } from '@knowii/client-ui';
import { mdxComponents } from '@knowii/client';
import { Layout } from '../../components/layout/layout';
import { getImageSize, getMdx, getMdxFilePaths } from '@knowii/server';
import { FrontMatter, I18N_TRANSLATIONS_BLOG, I18N_TRANSLATIONS_COMMON, WebsiteDataType } from '@knowii/common';
import { i18nConfig } from '../../../../next-i18next.config.mjs';

export const getStaticProps: GetStaticProps = async (ctx) => {
  const slug = ctx.params?.['slug'] as string;
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;

  const mdxProps = await getMdx({ type: WebsiteDataType.BLOG, slug, locale });

  // get cover image size
  const image = mdxProps?.frontMatter.image;
  if (image) {
    const imageSize = getImageSize({ imagePath: image });
    mdxProps!.frontMatter.imageDetails = {
      width: imageSize?.width ?? 1,
      height: imageSize?.height ?? 1,
      src: image,
    };
  }

  const translations = await serverSideTranslations(locale, [I18N_TRANSLATIONS_COMMON, I18N_TRANSLATIONS_BLOG], i18nConfig);

  return !mdxProps
    ? { redirect: { destination: '/blog', permanent: false } }
    : {
        props: {
          ...translations,
          ...mdxProps,
        },
        revalidate: 10,
      };
};

export const getStaticPaths: GetStaticPaths = async (ctx) => {
  // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
  const locales = ctx.locales!;

  const paths = await getMdxFilePaths({
    type: WebsiteDataType.BLOG,
    locales,
  });

  return {
    paths,
    fallback: true,
  };
};

interface BlogPostPageProps {
  source: MDXRemoteSerializeResult;
  frontMatter: FrontMatter;
}

/**
 * Renders a specific blog post
 * @param input
 * @constructor
 */
export function BlogPostPage({ source, frontMatter }: BlogPostPageProps) {
  const { t } = useTranslation(I18N_TRANSLATIONS_BLOG);
  const pageTitle = frontMatter.title ? `${frontMatter.title} - ${t('blog')}` : t('blog'); // FIXME change title
  const metaImage = useMemo(() => frontMatter.imageDetails?.src ?? null, [frontMatter]);

  return (
    <>
      {metaImage && (
        <Head>
          <meta property="og:image" content={metaImage} />
          <meta property="twitter:image" content={metaImage} />
        </Head>
      )}
      <Layout customMeta={{ title: pageTitle }}>
        <PageHeader title={frontMatter.title} align="center" containerMaxWidth="3xl">
          <VStack spacing={6} mt={6}>
            {frontMatter.imageDetails && (
              <Box rounded="2xl" overflow="hidden">
                <Image
                  src={frontMatter.imageDetails.src}
                  width={frontMatter.imageDetails.width}
                  height={frontMatter.imageDetails.height}
                  alt={frontMatter.title}
                  style={{
                    verticalAlign: 'middle',
                    maxWidth: '100%',
                    height: 'auto',
                  }}
                />
              </Box>
            )}
            <BlogPostMeta author={frontMatter.author ?? ''} authorImage={frontMatter.authorImage} publishedAt={frontMatter.publishedAt} />
          </VStack>
        </PageHeader>
        <Box px={4} py={12}>
          <Container maxW="3xl" w="full">
            {source && <MDXRemote components={mdxComponents} {...source} />}
          </Container>
        </Box>
      </Layout>
    </>
  );
}

export default BlogPostPage;
