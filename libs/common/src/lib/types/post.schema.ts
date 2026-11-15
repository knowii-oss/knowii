import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { followStateSchema } from './notification-follow.schema';
import { MAX_LENGTH_POST_BODY, MAX_LENGTH_POST_TITLE, MIN_LENGTH_POST_TITLE } from '../constants';

/**
 * Public summary of a member, next to what they wrote. WARNING: aligned with AuthorResource.php
 */
export const authorSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  username: z.string(),
  // Null without an avatar: the initials show (#109)
  avatar_url: z.string().nullable().optional(),
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
  // Set for the marker of a deleted post (#1344): no title, no text, no author
  deleted_at: z.coerce.date().nullable(),
  author: authorSchema.nullable(),
  // Comments and replies, when counted
  comments_count: z.number().int().optional(),
  // When loaded: count per emoji and whether the current member reacted
  reactions: z.array(z.object({ emoji: z.string(), count: z.number().int(), reacted: z.boolean() })).optional(),
  space_cuid: z.string(),
  // What the current member chose for the thread (#1497): following, muted, stopped, or null. Present when the server loaded it
  follow_state: followStateSchema.nullable().optional(),
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
 * What the API accepts to publish a post: `notify_now` tells the followers of the space at once, for the people who can
 * moderate the space (#1497)
 */
export type PostCreateInput = PostForm & { notify_now?: boolean };

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

/**
 * A post in the list of the newest posts on the public page of a community (#1485). WARNING: aligned with NewestPostResource.php
 */
export const newestPostSchema = z.object({
  cuid: z.string(),
  title: z.string(),
  slug: z.string(),
  excerpt: z.string(),
  // Null when the author deleted their account
  author: authorSchema.nullable(),
  space: z.object({ cuid: z.string(), slug: z.string(), name: z.string(), emoji: z.string() }),
  created_at: z.coerce.date(),
});
export type NewestPost = z.infer<typeof newestPostSchema>;
