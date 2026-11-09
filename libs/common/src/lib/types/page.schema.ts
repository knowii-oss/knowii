import { z } from 'zod';
import { MAX_LENGTH_POST_BODY, MAX_LENGTH_POST_TITLE, MIN_LENGTH_POST_TITLE } from '../constants';
import { authorSchema } from './post.schema';

/**
 * A page of a page space (#1179). Lists carry no body: get one page for its Markdown and its HTML.
 * WARNING: aligned with PageResource.php
 */
export const pageSchema = z.object({
  cuid: z.string(),
  title: z.string(),
  slug: z.string(),
  position: z.number().int(),
  body: z.string().nullable().optional(),
  body_html: z.string().nullable().optional(),
  author: authorSchema.nullable().optional(),
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

/**
 * A link to the previous or the next page
 */
export interface PageNeighbour {
  title: string;
  slug: string;
}
