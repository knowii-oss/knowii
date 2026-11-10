import type { ApiResult, ChatMessage, ChatThread, ReactionTarget } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';

/**
 * Where the messages of a chat room live: a chat space (#1185) or a conversation of direct messages (#1186). The room
 * is the same; only the API calls differ.
 */
export interface ChatBackend {
  list: (before?: string) => Promise<ApiResult<{ messages: ChatMessage[]; hasMore: boolean }>>;
  get: (messageCuid: string) => Promise<ApiResult<ChatMessage>>;
  // Threads: chat spaces only
  thread?: ((messageCuid: string) => Promise<ApiResult<ChatThread>>) | undefined;
  create: (body: string, parentCuid?: string) => Promise<ApiResult<ChatMessage>>;
  update: (messageCuid: string, body: string) => Promise<ApiResult<ChatMessage>>;
  remove: (messageCuid: string) => Promise<ApiResult<undefined>>;
  markAsRead: (messageCuid: string) => Promise<ApiResult<undefined>>;
  reactionTarget: (messageCuid: string) => ReactionTarget;
}

export function spaceChatBackend(communityCuid: string, spaceCuid: string): ChatBackend {
  const chat = knowiiApiClient.communities.chat;
  return {
    list: (before) => chat.list(communityCuid, spaceCuid, before),
    get: (messageCuid) => chat.get(communityCuid, spaceCuid, messageCuid),
    thread: (messageCuid) => chat.thread(communityCuid, spaceCuid, messageCuid),
    create: (body, parentCuid) => chat.create(communityCuid, spaceCuid, { body, ...(parentCuid ? { parent_cuid: parentCuid } : {}) }),
    update: (messageCuid, body) => chat.update(communityCuid, spaceCuid, messageCuid, { body }),
    remove: (messageCuid) => chat.delete(communityCuid, spaceCuid, messageCuid),
    markAsRead: (messageCuid) => chat.markAsRead(communityCuid, spaceCuid, messageCuid),
    reactionTarget: (messageCuid) => ({ communityCuid, spaceCuid, messageCuid }),
  };
}

export function conversationChatBackend(communityCuid: string, conversationCuid: string): ChatBackend {
  const conversations = knowiiApiClient.communities.conversations;
  return {
    list: (before) => conversations.messages(communityCuid, conversationCuid, before),
    get: (messageCuid) => conversations.message(communityCuid, conversationCuid, messageCuid),
    create: (body) => conversations.send(communityCuid, conversationCuid, { body }),
    update: (messageCuid, body) => conversations.update(communityCuid, conversationCuid, messageCuid, { body }),
    remove: (messageCuid) => conversations.delete(communityCuid, conversationCuid, messageCuid),
    markAsRead: (messageCuid) => conversations.markAsRead(communityCuid, conversationCuid, messageCuid),
    reactionTarget: (messageCuid) => ({ communityCuid, conversationCuid, messageCuid }),
  };
}
