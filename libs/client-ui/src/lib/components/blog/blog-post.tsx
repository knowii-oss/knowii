import { AspectRatio, Box, Button, Heading, Link, SimpleGrid, Text, useColorModeValue, VStack } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';
import Image from 'next/image';
import NextLink from 'next/link';
import { useMemo } from 'react';
import { FrontMatter, I18N_TRANSLATIONS_BLOG, RequiredPick } from '@knowii/common';
import { format, parseISO } from 'date-fns';

type BlogPostProps = RequiredPick<FrontMatter, 'slug' | 'title' | 'publishedOn' | 'summary' | 'image'>;

// FIXME rename to blog post overview
export function BlogPost({ slug, title, publishedOn, summary, image }: BlogPostProps) {
  const { t } = useTranslation(I18N_TRANSLATIONS_BLOG);

  const link = useMemo(() => `/blog/${slug}`, [slug]);

  return (
    <Box bg={useColorModeValue('white', 'gray.700')} rounded="3xl" overflow="hidden" p={4}>
      <SimpleGrid columns={{ base: 1, lg: 2 }} alignItems="center" gap={8}>
        {/* Cover image */}
        {image && (
          <AspectRatio ratio={1.6 / 1}>
            <Box as={NextLink} href={link} position="relative" rounded="xl" overflow="hidden">
              <Image
                src={image}
                alt={title}
                className=""
                fill={true}
                style={{
                  maxWidth: '100%',
                  objectFit: 'cover',
                }}
              />
            </Box>
          </AspectRatio>
        )}

        <Box>
          <VStack spacing={4} align="start">
            <VStack spacing={1} align="start">
              {/* Date */}
              <Text fontSize="sm" color="primary.300" textTransform="uppercase" fontWeight="bold">
                {format(parseISO(publishedOn), 'MMMM dd, yyyy')}
              </Text>
              {/* Title */}
              <Heading fontSize="2xl">
                <Link as={NextLink} href={link} textDecoration="none" _hover={{ color: 'primary.600' }}>
                  {title}
                </Link>
              </Heading>
            </VStack>

            {/* Summary (excerpt) */}
            <Text w="full">{summary}</Text>

            {/* Read more link */}
            <Button as={NextLink} href={link} variant="outline" colorScheme="primary">
              {t('readMore')} &rarr;
            </Button>
          </VStack>
        </Box>
      </SimpleGrid>
    </Box>
  );
}

export default BlogPost;
