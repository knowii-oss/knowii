import { z } from 'zod';
import { MAX_LENGTH_POST_BODY, MAX_LENGTH_POST_TITLE, MIN_LENGTH_POST_TITLE } from '../constants';
import { authorSchema } from './post.schema';

// A page (a lesson in a course space) or a section heading (#1188). WARNING: aligned with KnowiiPageKind.php
export const PAGE_KINDS = ['page', 'section'] as const;
export const pageKindSchema = z.enum(PAGE_KINDS);
export type PageKind = z.infer<typeof pageKindSchema>;

// The player of a lesson video (#1188). WARNING: aligned with VideoEmbed.php
export const pageVideoSchema = z.object({
  provider: z.enum(['youtube', 'vimeo']),
  id: z.string(),
  url: z.string(),
  embed_url: z.string(),
});
export type PageVideo = z.infer<typeof pageVideoSchema>;

/**
 * A page of a page space (#1179), or a lesson or a section of a course space (#1188). Lists carry no body: get one
 * page for its Markdown and its HTML. WARNING: aligned with PageResource.php
 */
export const pageSchema = z.object({
  cuid: z.string(),
  kind: pageKindSchema.default('page'),
  title: z.string(),
  slug: z.string(),
  position: z.number().int(),
  body: z.string().nullable().optional(),
  body_html: z.string().nullable().optional(),
  author: authorSchema.nullable().optional(),
  video: pageVideoSchema.nullable().optional(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type Page = z.infer<typeof pageSchema>;

// WARNING: aligned with CreatePage::rules()
export const pageFormSchema = z.object({
  title: z
    .string()
    .trim()
    .min(MIN_LENGTH_POST_TITLE, { error: `Use between ${MIN_LENGTH_POST_TITLE} and ${MAX_LENGTH_POST_TITLE} characters` })
    .max(MAX_LENGTH_POST_TITLE, { error: `The title is too long (maximum ${MAX_LENGTH_POST_TITLE} characters)` }),
  body: z
    .string()
    .min(1, { error: 'Write something' })
    .max(MAX_LENGTH_POST_BODY, { error: `The page is too long (maximum ${MAX_LENGTH_POST_BODY} characters)` }),
});

export type PageForm = z.infer<typeof pageFormSchema>;

// The address of a lesson video (#1188): YouTube or Vimeo, checked by the server. WARNING: aligned with VideoEmbed.php
const VIDEO_URL_REGEX = /^https?:\/\/(www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com|player\.vimeo\.com)\//i;

/**
 * A lesson of a course space (#1188): a page with an optional video
 */
export const lessonFormSchema = pageFormSchema.extend({
  video_url: z
    .string()
    .trim()
    .refine((value) => value === '' || VIDEO_URL_REGEX.test(value), { error: 'Use the address of a YouTube or Vimeo video' }),
});
export type LessonForm = z.infer<typeof lessonFormSchema>;

/**
 * A section of a page space or a course space (#1188): a title only
 */
export const sectionFormSchema = pageFormSchema.pick({ title: true });
export type SectionForm = z.infer<typeof sectionFormSchema>;

/**
 * A link to the previous or the next page
 */
export interface PageNeighbour {
  title: string;
  slug: string;
}

/**
 * The progress of the current member in a course space (#1304): the lessons marked as done and where to continue
 * (`next_lesson`: the first lesson that is not done, null when all are). WARNING: aligned with CourseProgress.php
 */
export const courseProgressSchema = z.object({
  total: z.number().int(),
  completed: z.number().int(),
  completed_lessons: z.array(z.string()),
  next_lesson: z.string().nullable(),
});
export type CourseProgress = z.infer<typeof courseProgressSchema>;
