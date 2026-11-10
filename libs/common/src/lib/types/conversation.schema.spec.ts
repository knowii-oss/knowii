import type { Conversation } from './conversation.schema';
import { conversationTitle, newConversationFormSchema } from './conversation.schema';

const person = (cuid: string, name: string) => ({ cuid, name, username: cuid, avatar_url: null });
const conversation = (names: string[]): Conversation => ({
  cuid: 'c1',
  is_direct: names.length === 2,
  participants: names.map((name, index) => person(index === 0 ? 'me' : `p${index}`, name)),
  last_message_at: null,
  created_at: new Date(),
});

describe('conversationTitle (#1186)', () => {
  it('names the other members, and stays short for a large group', () => {
    expect(conversationTitle(conversation(['Me', 'Ada']), 'me')).toBe('Ada');
    expect(conversationTitle(conversation(['Me', 'Ada', 'Bob', 'Cleo']), 'me')).toBe('Ada, Bob, Cleo');
    expect(conversationTitle(conversation(['Me', 'Ada', 'Bob', 'Cleo', 'Dan']), 'me')).toBe('Ada, Bob and 2 others');
    expect(conversationTitle(conversation(['Me']), 'me')).toBe('Only you');
  });

  it('needs one to nine other members and a message', () => {
    expect(newConversationFormSchema.safeParse({ participant_cuids: [], body: 'Hi' }).success).toBe(false);
    expect(newConversationFormSchema.safeParse({ participant_cuids: ['a'], body: ' ' }).success).toBe(false);
    expect(
      newConversationFormSchema.safeParse({ participant_cuids: Array.from({ length: 10 }, (_, i) => `m${i}`), body: 'Hi' }).success,
    ).toBe(false);
    expect(newConversationFormSchema.safeParse({ participant_cuids: ['a'], body: 'Hi' }).success).toBe(true);
  });
});
