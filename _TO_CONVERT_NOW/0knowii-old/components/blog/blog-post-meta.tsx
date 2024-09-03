import { Avatar, Box, HStack, Icon, Text, VStack } from '@chakra-ui/react';
import { FaCalendarAlt } from 'react-icons/fa';
import { FrontMatter, RequiredPick } from '@knowii/common';
import Link from 'next/link';
import { format, parseISO } from 'date-fns';
import { useTranslations } from 'next-intl';

type BlogPostMetaProps = RequiredPick<FrontMatter, 'author' | 'authorImage' | 'authorLink' | 'publishedOn' | 'readingTime'>;

export function BlogPostMeta({ author, authorImage, authorLink, publishedOn, readingTime }: BlogPostMetaProps) {
  const t = useTranslations('blogPostMeta');

  return (
    <Box>
      <HStack gap={8} lineHeight="short" fontSize="sm">
        <HStack>
          <Link href={authorLink} rel="noopener noreferrer" className="rounded-full">
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
              <time dateTime={format(parseISO(publishedOn), 'yyyy-MM-dd')}>
                {format(parseISO(publishedOn), 'MMMM dd, yyyy')} ({readingTime?.text})
              </time>
            </Text>
          </VStack>
        </HStack>
      </HStack>
    </Box>
  );
}

export default BlogPostMeta;
