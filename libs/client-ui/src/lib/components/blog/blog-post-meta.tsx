import { Avatar, Box, HStack, Icon, Text, VStack } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';
import { FaCalendarAlt } from 'react-icons/fa';
import { I18N_TRANSLATIONS_BLOG } from '@knowii/common';
import Link from 'next/link';

interface BlogPostMetaProps {
  author: string;
  authorImage?: string;
  authorLink: string;
  publishedAt: string;
}

export function BlogPostMeta({ author, authorImage, authorLink, publishedAt }: BlogPostMetaProps) {
  const { t } = useTranslation(I18N_TRANSLATIONS_BLOG);

  return (
    <Box>
      <HStack gap={8} lineHeight="short" fontSize="sm">
        <HStack>
          <Link href={authorLink} className="rounded-full">
            <Avatar name={author} src={authorImage} size="sm" />
          </Link>
          <VStack align="start" spacing={0}>
            <Text>{t('writtenBy')}</Text>
            <Text fontWeight="bold" as="span">
              {author}
            </Text>
          </VStack>
        </HStack>
        <HStack>
          <Icon fontSize="2xl" color="primary.300">
            <FaCalendarAlt />
          </Icon>
          <VStack align="start" spacing={0}>
            <Text>{t('publishedOn')}</Text>
            <Text fontWeight="bold" as="span">
              {publishedAt}
            </Text>
          </VStack>
        </HStack>
      </HStack>
    </Box>
  );
}

export default BlogPostMeta;
