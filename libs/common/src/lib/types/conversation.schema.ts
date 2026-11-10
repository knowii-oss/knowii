import { z } from 'zod';
import { MAX_CONVERSATION_PARTICIPANTS } from '../constants';
import { chatMessageFormSchema } from './chat-message.schema';
import { authorSchema } from './post.schema';

/**
 * A conversation of direct messages (#1186, ADR 0028). WARNING: aligned with ConversationResource.php
 */
export const conversationSchema = z.object({
  cuid: z.string(),
  is_direct: z.boolean(),
  participants: z.array(authorSchema).optional(),
  last_message: z
    .object({ cuid: z.string(), text: z.string(), author_name: z.string().nullable(), deleted: z.boolean() })
    .nullable()
    .optional(),
  last_message_at: z.coerce.date().nullable(),
  unread_count: z.number().int().optional(),
  created_at: z.coerce.date(),
});
export type Conversation = z.infer<typeof conversationSchema>;

// WARNING: aligned with StartConversation.php
export const newConversationFormSchema = chatMessageFormSchema.extend({
  participant_cuids: z
    .array(z.string())
    .min(1, { error: 'Choose at least one member' })
    .max(MAX_CONVERSATION_PARTICIPANTS - 1, { error: `A conversation has at most ${MAX_CONVERSATION_PARTICIPANTS} members, you included` }),
});
export type NewConversationForm = z.infer<typeof newConversationFormSchema>;

/**
 * The name of a conversation for the current member: the other participants
 */
export function conversationTitle(conversation: Conversation, currentUserCuid: string | null): string {
  const others = (conversation.participants ?? []).filter((participant) => participant.cuid !== currentUserCuid);
  if (others.length === 0) {
    return 'Only you';
  }
  if (others.length <= 3) {
    return others.map((participant) => participant.name).join(', ');
  }
  return `${others
    .slice(0, 2)
    .map((participant) => participant.name)
    .join(', ')} and ${others.length - 2} others`;
}
