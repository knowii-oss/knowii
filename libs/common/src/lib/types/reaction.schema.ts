import { z } from 'zod';
import { authorSchema } from './post.schema';

/**
 * WARNING: aligned with KnowiiReactionKind (back-end), in the same order
 */
export const REACTION_KINDS = ['like', 'love', 'insightful', 'funny', 'celebrate'] as const;
export const reactionKindSchema = z.enum(REACTION_KINDS);
export type ReactionKind = z.infer<typeof reactionKindSchema>;

export const REACTION_LABELS: Record<ReactionKind, { emoji: string; name: string }> = {
  like: { emoji: '👍', name: 'Like' },
  love: { emoji: '❤️', name: 'Love' },
  insightful: { emoji: '💡', name: 'Insightful' },
  funny: { emoji: '😄', name: 'Funny' },
  celebrate: { emoji: '🎉', name: 'Celebrate' },
};

/**
 * Count per kind and whether the current member reacted. WARNING: aligned with HasReactions::reactionSummary()
 */
export const reactionSummarySchema = z.array(
  z.object({
    kind: reactionKindSchema,
    count: z.number().int(),
    reacted: z.boolean(),
  }),
);
export type ReactionSummary = z.infer<typeof reactionSummarySchema>;

export const whoReactedSchema = z.array(z.object({ kind: reactionKindSchema, members: z.array(authorSchema) }));
export type WhoReacted = z.infer<typeof whoReactedSchema>;
