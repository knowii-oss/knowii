import matter from 'gray-matter';
import sizeOf from 'image-size';
import { MDXRemoteSerializeResult } from 'next-mdx-remote';
import { serialize } from 'next-mdx-remote/serialize';
import path from 'path';
import rehypeSlug from 'rehype-slug';
import rehypeImgSize from 'rehype-img-size';
import fs from 'fs';
import { FrontMatter, hasErrorMessage, WebsiteDataType } from '@knowii/common';
import readingTime from 'reading-time';

const APP_FOLDER = path.resolve(process.cwd(), 'apps/knowii');
const PUBLIC_FOLDER_PATH = path.join(APP_FOLDER, 'public');
const CONTENT_FOLDER_PATH = path.join(PUBLIC_FOLDER_PATH, 'content');

export function getFilesList({ type, locale }: { type: WebsiteDataType.BLOG; locale: string }): string[] {
  let retVal: string[] = [];

  const folderPath = path.join(CONTENT_FOLDER_PATH, type, locale);
  try {
    console.log('Loading files list from: ', folderPath);
    retVal = fs.readdirSync(folderPath);
    console.log('Files list: ', JSON.stringify(retVal));
  } catch {}

  return retVal;
}

/**
 * Load a specific file
 * @param type
 * @param slug
 * @param locale
 */
export async function getFileBySlug({
  type,
  slug,
  locale,
}: {
  type: WebsiteDataType;
  slug: string;
  locale: string;
}): Promise<string | null> {
  let source = null;

  const filePath = path.join(CONTENT_FOLDER_PATH, type, locale, `${slug}.mdx`);

  try {
    console.log('Trying to load file: ', filePath);
    source = await fs.readFileSync(filePath, 'utf8');
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      console.warn('Error while loading the file: ', err.message);
    } else {
      console.warn('Error while loading the file: ', err);
    }
  }

  return source;
}

export interface MdxEntry {
  slug: string;
  mdxSource: MDXRemoteSerializeResult;
  frontMatter: FrontMatter;
}

/**
 * Get the size of the given image
 * @param imagePath
 */
export function getImageSize({ imagePath }: { imagePath: string }) {
  const filePath = path.join(PUBLIC_FOLDER_PATH, imagePath);

  return sizeOf(filePath);
}

export async function getMdx({ type, slug, locale }: { type: WebsiteDataType; slug: string; locale: string }): Promise<MdxEntry | null> {
  const fileContent = await getFileBySlug({
    type,
    slug,
    locale,
  });

  if (!fileContent) {
    console.log('Could not find MDX file content');
    return null;
  }

  const { content, data } = matter(fileContent);

  const mdxSource: MDXRemoteSerializeResult = await serialize(content, {
    mdxOptions: {
      rehypePlugins: [
        [rehypeImgSize as any, { dir: `public/images/${type}` }], // FIXME fix this path
        /**
         * Add id to post headings
         * Reference: https://github.com/rehypejs/rehype-slug
         */
        rehypeSlug,
      ],
      remarkPlugins: [require('remark-code-titles')],
    },
  });

  const frontMatter: FrontMatter = {
    wordCount: content.split(/\s+/gu).length,
    readingTime: readingTime(content),
    slug: slug || '',
    title: '',
    publishedOn: '',
    summary: '',
    image: '',
    categories: [],
    keywords: [],
    published: true,
    ...data,
  };

  const retVal: MdxEntry = {
    slug,
    mdxSource,
    frontMatter,
  };

  return retVal;
}

export async function getMdxFilePaths({ type, locales }: { type: WebsiteDataType; locales: string[] }) {
  const paths: Array<{
    params: any;
    locale?: string;
  }> = [];
  await Promise.all(
    locales.map(async (locale) => {
      try {
        const files = getFilesList({ type, locale });
        files.forEach((file) => {
          if (!file.endsWith('.mdx')) {
            console.warn('Only .mdx files should be stored in the content folder!');
            return;
          }

          paths.push({ params: { slug: file.replace('.mdx', ''), locale } });
        });
      } catch {}
    }),
  );

  return paths;
}

export async function getAllMdxEntries({
  type,
  locales,
  defaultLocale,
  currentLocale,
}: {
  type: WebsiteDataType;
  locales: string[];
  defaultLocale: string;
  currentLocale?: string;
}): Promise<MdxEntry[]> {
  const paths = await getMdxFilePaths({
    type,
    locales,
  });

  const entries = (
    await Promise.all(
      paths.map(async ({ params: { slug } }) => {
        return await getMdx({ type, slug, locale: currentLocale ? currentLocale : defaultLocale });
      }),
    )
  ).filter((entry) => entry !== null);

  return entries as MdxEntry[];
}
