import { Box, Divider, Heading, Link, ListItem, OrderedList, Text, UnorderedList, useColorModeValue } from '@chakra-ui/react';
import Image from 'next/image';
import NextLink from 'next/link';
import * as prism from 'prismjs';
import 'prismjs/components/prism-typescript';
import React, { PropsWithChildren, useMemo } from 'react';
import {
  BlogPostCenteredContent,
  BlogPostNote,
  BlogPostQuote,
  BlogPostSignature,
  BlogPostWarning,
  BlogPostTip,
  Step,
} from '@knowii/client-ui';

// FIXME replace once this is solved: https://github.com/PaulieScanlon/mdx-embed/issues/247
/// <reference types="../../../../mdx-embed" />
import { Gist } from 'mdx-embed/dist/components/gist';
import { CodePen } from 'mdx-embed/dist/components/codepen';
import { Spotify } from 'mdx-embed/dist/components/spotify';
import { Twitch } from 'mdx-embed/dist/components/twitch';
import { Tweet, TwitterFollowButton } from 'mdx-embed/dist/components/twitter';
import { YouTube } from 'mdx-embed/dist/components/youtube';

/*
  Headings
*/
const h1 = (props: any) => <Heading fontSize="4xl" mb={4} {...props} />;
const h2 = (props: any) => <Heading fontSize="2xl" mb={4} {...props} />;
const h3 = (props: any) => <Heading fontSize="xl" mb={4} {...props} />;
const h4 = (props: any) => <Heading fontSize="lg" mb={4} {...props} />;
const h5 = (props: any) => <Heading fontSize="base" mb={4} {...props} />;
const h6 = (props: any) => <Heading fontSize="sm" mb={4} {...props} />;
/*
  Paragraph
*/
function CustomParagraph(props: any) {
  return <Text mb={6} lineHeight={1.7} {...props} color={useColorModeValue('gray.600', 'gray.300')} />;
}
/*
  Lists
*/
const CustomUl = (props: any) => <UnorderedList spacing={2} mb={6} color={useColorModeValue('gray.600', 'gray.300')} {...props} />;
const CustomLi = (props: any) => <ListItem color={useColorModeValue('gray.600', 'gray.300')} {...props} />;
const ol = (props: any) => <OrderedList spacing={2} mb={6} {...props} />;
/*
  Divider
*/
const hr = (props: any) => <Divider {...props} my={8} />;
/*
  Code
*/
function CustomCode({ children, className }: PropsWithChildren<any>) {
  const language = className?.replace('language-', '') ?? 'typescript';
  const highlighted = useMemo(() => prism.highlight(children as string, prism.languages[language], language), [children, language]);
  return <Box as="code" bg="gray.800" rounded="md" p={1} color="white" dangerouslySetInnerHTML={{ __html: highlighted }}></Box>;
}
const pre = ({ children }: PropsWithChildren<any>) => (
  <Box as="pre" mb={6} rounded="lg" bg="gray.800" p={4} maxW="full" overflow="auto">
    {children}
  </Box>
);
/*
  Image
*/
const CustomImage = (props: any) => (
  <Image
    {...props}
    alt={props.alt}
    loading="lazy"
    sizes="100vw"
    style={{
      width: '100%',
      height: 'auto',
    }}
  />
);
/*
  Link
*/
type CustomLinkProps = { href: string } & any;

const CustomLink = (props: CustomLinkProps) => {
  const href = props.href;
  const isInternalLink = href && (href.startsWith('/') || href.startsWith('#'));

  if (isInternalLink) {
    return (
      <Link as={NextLink} href={href} {...props}>
        {props.children}
      </Link>
    );
  }

  return (
    <Link target="_blank" rel="noopener noreferrer" {...props}>
      {props.children}
    </Link>
  );
};

export const mdxComponents = {
  a: CustomLink,
  h1,
  h2,
  h3,
  h4,
  h5,
  h6,
  ul: CustomUl,
  ol,
  li: CustomLi,
  hr,
  img: CustomImage,
  p: CustomParagraph,
  code: CustomCode,
  pre,
  Gist,
  CodePen,
  Spotify,
  Twitch,
  Tweet,
  YouTube,
  TwitterFollowButton,
  BlogPostCenteredContent,
  BlogPostNote,
  BlogPostQuote,
  BlogPostSignature,
  BlogPostTip,
  BlogPostWarning,
  Step,
};
