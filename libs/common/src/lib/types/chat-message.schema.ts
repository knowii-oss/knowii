import { z } from 'zod';
import { MAX_LENGTH_CHAT_MESSAGE } from '../constants';
import { authorSchema } from './post.schema';
import { reactionSummarySchema } from './reaction.schema';

/**
 * A message of a chat space (#1185). A deleted message is a marker: no text, no author.
 * WARNING: aligned with ChatMessageResource.php
 */
export const chatMessageSchema = z.object({
  cuid: z.string(),
  body: z.string().nullable(),
  body_html: z.string().nullable(),
  author: authorSchema.nullable(),
  parent_cuid: z.string().nullable(),
  reply_count: z.number().int(),
  last_reply_at: z.coerce.date().nullable(),
  edited_at: z.coerce.date().nullable(),
  deleted_at: z.coerce.date().nullable(),
  reactions: reactionSummarySchema.optional(),
  created_at: z.coerce.date(),
});

export type ChatMessage = z.infer<typeof chatMessageSchema>;

// WARNING: aligned with CreateChatMessage::rules()
export const chatMessageFormSchema = z.object({
  body: z
    .string()
    .trim()
    .min(1, { error: 'Write something' })
    .max(MAX_LENGTH_CHAT_MESSAGE, { error: `The message is too long (maximum ${MAX_LENGTH_CHAT_MESSAGE} characters)` }),
});

export type ChatMessageForm = z.infer<typeof chatMessageFormSchema>;

/**
 * A thread: its first message and the replies, oldest first
 */
export const chatThreadSchema = z.object({
  message: chatMessageSchema,
  replies: z.array(chatMessageSchema),
});

export type ChatThread = z.infer<typeof chatThreadSchema>;
