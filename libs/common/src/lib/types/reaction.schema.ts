import { z } from 'zod';
import { authorSchema } from './post.schema';

/**
 * The quick reactions, first in the picker (#1204). WARNING: aligned with Emoji::QUICK (back-end)
 */
export const QUICK_REACTIONS = [
  { emoji: '👍', name: 'Like' },
  { emoji: '❤️', name: 'Love' },
  { emoji: '💡', name: 'Insightful' },
  { emoji: '😄', name: 'Funny' },
  { emoji: '🎉', name: 'Celebrate' },
] as const;

/**
 * Different emoji on one item, at most. WARNING: aligned with Emoji::MAX_PER_ITEM (back-end)
 */
export const MAX_REACTION_EMOJI_PER_ITEM = 20;

/**
 * An emoji of the picker. The server checks it against its allow-list; the client only checks the shape
 */
export const reactionEmojiSchema = z.string().min(1).max(64);

/**
 * Count per emoji, in the order of the first reaction, and whether the current member reacted.
 * WARNING: aligned with HasReactions::reactionSummary()
 */
export const reactionSummarySchema = z.array(
  z.object({
    emoji: reactionEmojiSchema,
    count: z.number().int(),
    reacted: z.boolean(),
  }),
);
export type ReactionSummary = z.infer<typeof reactionSummarySchema>;

export const whoReactedSchema = z.array(z.object({ emoji: reactionEmojiSchema, members: z.array(authorSchema) }));
export type WhoReacted = z.infer<typeof whoReactedSchema>;
