import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { authorSchema } from './post.schema';
import { MAX_LENGTH_COMMENT_BODY } from '../constants';

/**
 * WARNING: aligned with CommentResource.php
 */
const commentBaseSchema = baseEntitySchema.extend({
  body: z.string(),
  body_html: z.string(),
  edited_at: z.coerce.date().nullable(),
  author: authorSchema.nullable(),
  // null for a top-level comment
  parent_cuid: z.string().nullable(),
  reactions: z.array(z.object({ kind: z.string(), count: z.number().int(), reacted: z.boolean() })).optional(),
});

export type Comment = z.infer<typeof commentBaseSchema> & { replies?: Comment[] | undefined };

export const commentSchema: z.ZodType<Comment> = commentBaseSchema.extend({
  replies: z.lazy(() => z.array(commentSchema)).optional(),
});

/**
 * WARNING: aligned with CreateComment.php
 */
export const commentFormSchema = z.object({
  body: z
    .string()
    .trim()
    .min(1, { error: 'Write something' })
    .max(MAX_LENGTH_COMMENT_BODY, { error: `The comment is too long (maximum ${MAX_LENGTH_COMMENT_BODY} characters)` }),
});
export type CommentForm = z.infer<typeof commentFormSchema>;
