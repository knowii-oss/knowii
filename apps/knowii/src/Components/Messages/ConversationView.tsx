import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import type { ChatMessage, Conversation } from '@knowii/common';
import { conversationPresenceChannel, conversationTitle, knowiiApiClient, useAppData } from '@knowii/common';
import { FaBan } from 'react-icons/fa6';
import { ChatRoom } from '@/Components/Chat/ChatRoom';
import { conversationChatBackend } from '@/Components/Chat/chat-backend';
import { Button } from '@/Components/ui';

export interface ConversationViewProps {
  communityCuid: string;
  communitySlug: string;
  conversation: Conversation;
  // WARNING: aligned with ConversationController.php
  messages: { items: ChatMessage[]; has_more: boolean; first_unread_cuid: string | null };
  currentUserCuid: string;
  canSend: boolean;
  // One-to-one conversations: whether the current member blocked the other one
  blockedByMe: boolean;
  directMessagesEnabled: boolean;
  showsOnlineStatus: boolean;
}

// Hidden members see no typing indicators in conversations: there is no list to read
const noMembers = () => Promise.resolve({ ok: true as const, message: '', data: [] });

/**
 * One conversation of direct messages (#1186): its members, its messages as a chat room without threads, typing
 * indicators (#1187), and Block or Unblock for a one-to-one conversation.
 */
export function ConversationView(props: ConversationViewProps) {
  const { conversation, currentUserCuid } = props;
  const { showToast } = useAppData();
  const [busy, setBusy] = useState(false);
  const backend = useMemo(() => conversationChatBackend(props.communityCuid, conversation.cuid), [props.communityCuid, conversation.cuid]);
  const title = conversationTitle(conversation, currentUserCuid);
  const other = conversation.is_direct
    ? (conversation.participants ?? []).find((participant) => participant.cuid !== currentUserCuid)
    : undefined;

  const readOnlyReason = props.blockedByMe
    ? `You blocked ${other?.name ?? 'this member'}. Unblock them to write again.`
    : !props.directMessagesEnabled
      ? 'Direct messages are turned off in this community. You can still read this conversation.'
      : 'You cannot send messages in this conversation.';

  const toggleBlock = async () => {
    if (!other) {
      return;
    }
    setBusy(true);
    const result = props.blockedByMe
      ? await knowiiApiClient.users.blocks.unblock(other.cuid)
      : await knowiiApiClient.users.blocks.block(other.cuid);
    setBusy(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: props.blockedByMe ? `${other.name} is unblocked` : `${other.name} is blocked` });
    router.reload({ only: ['permissions', 'blockedByMe'] });
  };

  return (
    <section aria-labelledby="conversation-title" className="flex flex-col gap-3">
      <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
        <div className="min-w-0">
          <h2 id="conversation-title" className="truncate text-lg font-extrabold">
            {title}
          </h2>
          {!conversation.is_direct && (
            <p className="text-xs text-muted">{(conversation.participants ?? []).length} members, you included</p>
          )}
        </div>
        {other && (
          <Button variant="ghost" size="sm" onClick={() => void toggleBlock()} loading={busy}>
            <FaBan aria-hidden="true" />
            {props.blockedByMe ? `Unblock ${other.name}` : `Block ${other.name}`}
          </Button>
        )}
      </header>
      <ChatRoom
        key={conversation.cuid}
        communityCuid={props.communityCuid}
        communitySlug={props.communitySlug}
        label={`Conversation with ${title}`}
        composerLabel={`Message ${title}`}
        backend={backend}
        live={{ type: 'conversation', conversationCuid: conversation.cuid }}
        initialMessages={props.messages.items}
        hasMore={props.messages.has_more}
        firstUnreadCuid={props.messages.first_unread_cuid}
        currentUserCuid={currentUserCuid}
        canWrite={props.canSend}
        canReact={props.canSend}
        canModerate={false}
        readOnlyReason={readOnlyReason}
        presence={{
          channel: conversationPresenceChannel(conversation.cuid),
          visible: props.showsOnlineStatus,
          fetchMembers: noMembers,
          showHereNow: false,
        }}
      />
    </section>
  );
}
