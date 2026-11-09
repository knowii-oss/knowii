import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { MAX_LENGTH_POST_BODY, MAX_LENGTH_POST_TITLE, MIN_LENGTH_POST_TITLE } from '../constants';

/**
 * Public summary of a member, next to what they wrote. WARNING: aligned with AuthorResource.php
 */
export const authorSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  username: z.string(),
});
export type Author = z.infer<typeof authorSchema>;

/**
 * WARNING: aligned with PostResource.php
 */
export const postSchema = baseEntitySchema.extend({
  title: z.string(),
  slug: z.string(),
  // Only present when the post is loaded alone (to edit it)
  body: z.string().optional(),
  body_html: z.string(),
  is_pinned: z.boolean(),
  pinned_at: z.coerce.date().nullable(),
  edited_at: z.coerce.date().nullable(),
  author: authorSchema.nullable(),
  // Comments and replies, when counted
  comments_count: z.number().int().optional(),
  space_cuid: z.string(),
});
export type Post = z.infer<typeof postSchema>;

/**
 * WARNING: aligned with CreatePost::rules()
 */
export const postFormSchema = z.object({
  title: z
    .string()
    .trim()
    .min(MIN_LENGTH_POST_TITLE, { error: `Use between ${MIN_LENGTH_POST_TITLE} and ${MAX_LENGTH_POST_TITLE} characters` })
    .max(MAX_LENGTH_POST_TITLE, { error: `The title is too long (maximum ${MAX_LENGTH_POST_TITLE} characters)` }),
  body: z
    .string()
    .min(1, { error: 'Write something' })
    .max(MAX_LENGTH_POST_BODY, { error: `The post is too long (maximum ${MAX_LENGTH_POST_BODY} characters)` }),
});
export type PostForm = z.infer<typeof postFormSchema>;

/**
 * Offset pagination metadata of the NBB REST API Design Guide (ADR 0011). WARNING: aligned with Pagination.php
 */
export const paginationSchema = z.object({
  limit: z.number().int(),
  offset: z.number().int(),
  previousOffset: z.number().int().nullable(),
  nextOffset: z.number().int().nullable(),
  currentPage: z.number().int().nullable(),
  pageCount: z.number().int(),
  totalCount: z.number().int(),
});
export type Pagination = z.infer<typeof paginationSchema>;

export interface Paginated<T> {
  items: T[];
  pagination: Pagination;
}

export interface PostPermissions {
  // WARNING: aligned with PostController.php
  canManageSpaces: boolean;
  canContribute: boolean;
  canModerate: boolean;
  canUpdatePost: boolean;
  canDeletePost: boolean;
  canPinPost: boolean;
  canComment: boolean;
}
